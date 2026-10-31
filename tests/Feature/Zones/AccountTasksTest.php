<?php

declare(strict_types=1);

/**
 * This file is part of simplysmart/skeleton13.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Tests\Feature\Zones;

use Simplysmart\Admiro\Enums\TaskStatus;
use Simplysmart\Admiro\Models\AccountTask;
use Simplysmart\Admiro\Tasks\AccountTasks;
use Simplysmart\Admiro\Zones\ZoneNavigations;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\ZoneShopNavigation;

/**
 * Zadania TODO konta: komenda `todo:` z pola komend na dole menu błyskawicy zapisuje zadanie z kolejnym numerem
 * zgłoszenia konta, lista pod powiadomieniami w panelu bocznym layoutu stref przełącza wykonanie i ukrywa zrealizowane,
 * rozpoczęte zadanie jest w toku, nazwa zadania otwiera modal jego edycji, a zadania innych kont są niedostępne;
 * komenda `wiki:` prowadzi do wyszukiwania na stronie wiki strefy.
 */
class AccountTasksTest extends ZoneTestCase
{
    private const string TASKS = '/alpha/account/tasks';

    public function test_todo_command_saves_open_tasks_with_the_next_issue_number_of_the_account(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->signedIn($account, 'alpha');

        $this->from('/alpha/page')->post(self::TASKS, ['command' => 'todo', 'text' => '  Zadzwonić do klienta '])
            ->assertRedirect('/alpha/page')->assertSessionHas('status', text('account.task_added'));
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Wysłać ofertę']);

        $tasks = AccountTask::query()->orderBy('issue')->get();
        $this->assertSame([[1, 'Zadzwonić do klienta'], [2, 'Wysłać ofertę']], $tasks->map(static fn(AccountTask $task): array => [$task->issue, $task->name])->all());
        $first = $tasks->firstOrFail();
        $this->assertSame(TaskStatus::Open, $first->status);
        $this->assertSame('alpha', $first->zone);
        $this->assertSame($account->getKey(), $first->account_id);
    }

    public function test_task_keeps_the_address_of_the_page_it_was_saved_from_without_showing_it(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $this->withHeader('Referer', url('/alpha/page?tab=clinics'))->postJson(self::TASKS, ['command' => 'todo', 'text' => 'Poprawić zakładkę'])->assertOk();
        $this->flushHeaders()->postJson(self::TASKS, ['command' => 'todo', 'text' => 'Bez adresu strony'])->assertOk();

        $tasks = AccountTask::query()->orderBy('issue')->get();
        $this->assertSame([url('/alpha/page?tab=clinics'), null], $tasks->map(static fn(AccountTask $task): ?string => $task->sourceUrl())->all());
        $this->assertStringNotContainsString('tab=clinics', (string) $this->get('/alpha/page')->assertOk()->getContent());
    }

    public function test_unknown_command_or_empty_text_is_rejected(): void
    {
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $this->post(self::TASKS, ['command' => 'deploy', 'text' => 'Zadanie'])->assertRedirect();
        $this->assertNotSame('', $this->sessionError('command'));
        $this->post(self::TASKS, ['command' => 'todo', 'text' => ' '])->assertRedirect();
        $this->assertNotSame('', $this->sessionError('text'));
        $this->assertSame(0, AccountTask::query()->count());
    }

    public function test_task_is_marked_done_and_reopened_only_by_its_account(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $owner = $this->account(AlphaAccount::class);
        $this->signedIn($owner, 'alpha');
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Zadzwonić']);
        $task = AccountTask::query()->sole();

        $this->patch(self::TASKS . '/' . $task->token)->assertSessionHas('status', text('account.task_done'));
        $this->assertSame(TaskStatus::Done, $task->refresh()->status);
        $this->assertNotNull($task->refresh()->completed_at);
        $this->assertMatchesRegularExpression('~<div class="ss-tasks__area" id="ss-tasks-panel-pending"[^>]*>\s*<p class="ss-notifications__empty">.*?<div class="ss-tasks__area" id="ss-tasks-panel-delivered"[^>]*>.*?<li class="ss-task ss-task--done">.*?id="ss-task-panel-' . $task->token . '" type="checkbox"\s+checked>~s', (string) $this->get('/alpha/page')->getContent(), 'Wykonane zadanie jest w obszarze „do sprawdzenia i wdrożone”.');

        $this->patch(self::TASKS . '/' . $task->token)->assertSessionHas('status', text('account.task_reopened'));
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
        $this->assertNull($task->refresh()->completed_at);

        $this->signedIn($this->account(AlphaAccount::class, ['email' => 'other@example.com']), 'alpha');
        $this->patch(self::TASKS . '/' . $task->token)->assertNotFound();
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    public function test_task_is_edited_from_its_modal_only_by_its_account(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Zadzwonić']);
        $task = AccountTask::query()->sole();

        $html = (string) $this->get('/alpha/page')->getContent();
        $this->assertMatchesRegularExpression('~<dialog class="ss-dialog" id="ss-task-edit-panel-' . $task->token . '".*?<form class="ss-form" method="POST" action="' . preg_quote(url(self::TASKS . '/' . $task->token), '~') . '">.*?name="_method" value="PUT".*?name="name"[^>]*value="Zadzwonić"~s', $html, 'Nazwa zadania otwiera modal z formularzem edycji.');

        $this->put(self::TASKS . '/' . $task->token, ['name' => ' Zadzwonić do klienta ', 'title' => 'Klient', 'description' => ''])
            ->assertSessionHas('status', text('account.task_updated'));
        $task->refresh();
        $this->assertSame('Zadzwonić do klienta', $task->name);
        $this->assertSame('Klient', $task->title);
        $this->assertNull($task->description);
        $this->assertSame(TaskStatus::Open, $task->status);
        $this->assertSame(1, $task->issue);

        $this->put(self::TASKS . '/' . $task->token, ['name' => ''])->assertSessionHasErrors('name');

        $this->signedIn($this->account(AlphaAccount::class, ['email' => 'other@example.com']), 'alpha');
        $this->put(self::TASKS . '/' . $task->token, ['name' => 'Cudze'])->assertNotFound();
        $this->assertSame('Zadzwonić do klienta', $task->refresh()->name);
    }

    public function test_started_task_is_in_progress_and_stays_on_the_list_until_done(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Poprawić listę']);
        $task = $this->app->make(AccountTasks::class)->start(AccountTask::query()->sole());

        $this->assertSame(TaskStatus::InProgress, $task->refresh()->status);
        $this->assertMatchesRegularExpression('~<span class="ss-tasks__count">1</span>.*?<button class="ss-task__name"[^>]*>Poprawić listę</button>\s*<span class="ss-badge ss-badge--outline ss-badge--warning ss-task__status">' . preg_quote(TaskStatus::InProgress->label(), '~') . '</span>~s', (string) $this->get('/alpha/page')->getContent());

        $this->patch(self::TASKS . '/' . $task->token)->assertSessionHas('status', text('account.task_done'));
        $this->assertSame(TaskStatus::Done, $task->refresh()->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_task_submitted_for_check_shows_its_commits_in_the_delivered_area_until_done(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Poprawić teczkę']);
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Nowa lista']);
        $tasks = $this->app->make(AccountTasks::class);
        $task = $tasks->submitForCheck(AccountTask::query()->where('issue', 1)->sole(), ['https://github.com/acme/core/commit/0123456789abcdef', 'https://github.com/acme/core/commit/0123456789abcdef', 'https://example.org/build/7']);

        $this->assertSame(TaskStatus::Check, $task->refresh()->status);
        $this->assertSame(['https://github.com/acme/core/commit/0123456789abcdef', 'https://example.org/build/7'], $task->commits());
        $html = (string) $this->get('/alpha/page')->getContent();
        $this->assertMatchesRegularExpression('~<span class="ss-tasks__count">1</span>.*?id="ss-tasks-panel-tab-pending"[^>]*>.*?<span class="ss-tasks__tab-count">1</span>.*?id="ss-tasks-panel-tab-delivered"[^>]*>.*?<span class="ss-tasks__tab-count">1</span>~s', $html);
        $this->assertMatchesRegularExpression('~id="ss-tasks-panel-pending".*?Nowa lista.*?id="ss-tasks-panel-delivered".*?Poprawić teczkę</button>\s*<span class="ss-badge ss-badge--outline ss-badge--muted ss-task__status">' . preg_quote(TaskStatus::Check->label(), '~') . '</span>.*?<a class="ss-task__commit" href="https://github.com/acme/core/commit/0123456789abcdef" target="_blank" rel="noopener noreferrer"[^>]*>.*?core@0123456</a>.*?<a class="ss-task__commit" href="https://example.org/build/7"[^>]*>.*?https://example.org/build/7</a>~s', $html);

        $this->patch(self::TASKS . '/' . $task->token)->assertSessionHas('status', text('account.task_done'));
        $this->assertSame(TaskStatus::Done, $task->refresh()->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_zone_layout_has_the_command_field_in_the_lightning_menu_and_the_tasks_under_the_notifications(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Przygotować raport']);

        $html = (string) $this->get('/alpha/page')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<details class="ss-search__menu ss-search__menu--folder ss-search__find".*?<div class="ss-search__folder-sheet">.*?</div>\s*<div class="ss-search__folder-foot">\s*<div class="ss-command" data-ss-command>.*?<span class="ss-icon ss-command__zap"><svg[^>]*>.*?</svg>\s*</span>\s*<span class="ss-command__current" data-ss-command-current>todo:</span>.*?<input class="ss-command__radio" type="radio" name="command" value="todo" form="ss-command-form"\s+checked.*?<input class="ss-command__input" id="ss-command-text" name="text" type="text" form="ss-command-form"[^>]*placeholder="' . preg_quote(text('enums.quick_command.todo.placeholder'), '~') . '"~s', $html);
        $this->assertStringContainsString('<form class="ss-command__form" id="ss-command-form" method="POST" action="' . url(self::TASKS) . '" hidden data-ss-command-form>', $html);
        $this->assertSame(1, substr_count($html, 'id="ss-command-text"'), 'Pole komend stoi tylko w menu błyskawicy, nie w menu „+”.');
        $this->assertMatchesRegularExpression('~data-ss-side-panel="notifications">.*?<div class="ss-side-panels__split-top">.*?ss-notifications__tabs-wrap.*?<div class="ss-side-panels__split-bottom">\s*<section class="ss-tasks".*?<span class="ss-task__issue">#1</span>\s*<button class="ss-task__name" type="button" title="' . preg_quote(text('layout.task_edit'), '~') . '" data-ss-dialog-open="ss-task-edit-panel-[^"]+">Przygotować raport</button>~s', $html);
    }

    public function test_command_sent_by_the_script_gets_the_message_and_the_refreshed_task_list_instead_of_a_redirect(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $response = $this->postJson(self::TASKS, ['command' => 'todo', 'text' => 'Wysłać ofertę'])->assertOk()->assertJsonPath('message', text('account.task_added'));

        $this->assertSame('Wysłać ofertę', AccountTask::query()->sole()->name);
        $tasks = $response->json('tasks');
        $this->assertIsString($tasks);
        $this->assertMatchesRegularExpression('~^\s*<section class="ss-tasks".*?<button class="ss-task__name"[^>]*>Wysłać ofertę</button>.*</section>\s*$~s', $tasks);
        $this->assertSame($tasks, $response->json('lists.panel'));
        $sidebar = $response->json('lists.sidebar');
        $this->assertIsString($sidebar);
        $this->assertStringContainsString('<section class="ss-tasks" aria-labelledby="ss-tasks-sidebar-heading" data-ss-tasks="sidebar">', $sidebar);
        $this->postJson(self::TASKS, ['command' => 'todo', 'text' => ''])->assertUnprocessable();
    }

    public function test_wiki_command_leads_to_the_zone_wiki_search_instead_of_saving_a_task(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $this->post(self::TASKS, ['command' => 'wiki', 'text' => ' departament '])->assertRedirect('/alpha/account/wiki?q=departament');
        $this->postJson(self::TASKS, ['command' => 'wiki', 'text' => 'strefa'])->assertOk()->assertExactJson(['redirect' => url('/alpha/account/wiki?q=strefa')]);
        $this->assertSame(0, AccountTask::query()->count());

        $found = (string) $this->get('/alpha/account/wiki?q=departament')->assertOk()->getContent();
        $this->assertStringContainsString('id="wiki-departament"', $found);
        $this->assertStringNotContainsString('id="wiki-strefa"', $found);
        $this->get('/alpha/account/wiki?q=nieistniejace')->assertOk()->assertSee(text('wiki.none', ['term' => 'nieistniejace']));
        $this->assertMatchesRegularExpression('~value="wiki" form="ss-command-form"[^>]*data-prefix="wiki:"~', (string) $this->get('/alpha/page')->getContent(), 'Komenda wiki: jest w polu komend błyskawicy.');
    }

    public function test_guest_cannot_add_tasks(): void
    {
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Zadanie'])->assertRedirect('/alpha/signin');
        $this->assertSame(0, AccountTask::query()->count());
    }
}
