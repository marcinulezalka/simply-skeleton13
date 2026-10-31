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
use Simplysmart\Admiro\Zones\ZoneNavigations;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\ZoneShopNavigation;

/**
 * Zadania TODO konta: komenda `todo:` z pola komend na dole menu błyskawicy zapisuje zadanie z kolejnym numerem
 * zgłoszenia konta, lista pod powiadomieniami w panelu bocznym layoutu stref przełącza wykonanie, a zadania innych
 * kont są niedostępne.
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
        $this->assertMatchesRegularExpression('~<li class="ss-task ss-task--done">.*?<input class="ss-task__check" id="ss-task-' . $task->token . '" type="checkbox"\s+checked>~s', (string) $this->get('/alpha/page')->getContent());

        $this->patch(self::TASKS . '/' . $task->token)->assertSessionHas('status', text('account.task_reopened'));
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
        $this->assertNull($task->refresh()->completed_at);

        $this->signedIn($this->account(AlphaAccount::class, ['email' => 'other@example.com']), 'alpha');
        $this->patch(self::TASKS . '/' . $task->token)->assertNotFound();
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    public function test_zone_layout_has_the_command_field_in_the_lightning_menu_and_the_tasks_under_the_notifications(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Przygotować raport']);

        $html = (string) $this->get('/alpha/page')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<details class="ss-search__menu ss-search__menu--folder ss-search__find".*?<div class="ss-search__folder-sheet">.*?</div>\s*<div class="ss-search__folder-foot">\s*<div class="ss-command" data-ss-command>.*?<span class="ss-command__slash" aria-hidden="true">/</span>\s*<span class="ss-command__current" data-ss-command-current>todo:</span>.*?<input class="ss-command__radio" type="radio" name="command" value="todo" form="ss-command-form"\s+checked.*?<input class="ss-command__input" id="ss-command-text" name="text" type="text" form="ss-command-form"[^>]*placeholder="' . preg_quote(text('enums.quick_command.todo.placeholder'), '~') . '"~s', $html);
        $this->assertStringContainsString('<form class="ss-command__form" id="ss-command-form" method="POST" action="' . url(self::TASKS) . '" hidden>', $html);
        $this->assertMatchesRegularExpression('~data-ss-side-panel="notifications">.*?<div class="ss-side-panels__split-top">.*?ss-notifications__tabs-wrap.*?<div class="ss-side-panels__split-bottom">\s*<section class="ss-tasks".*?<span class="ss-task__issue">#1</span>\s*<span class="ss-task__name">Przygotować raport</span>~s', $html);
    }

    public function test_command_sent_by_the_script_gets_the_message_and_the_refreshed_task_list_instead_of_a_redirect(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $response = $this->postJson(self::TASKS, ['command' => 'todo', 'text' => 'Wysłać ofertę'])->assertOk()->assertJsonPath('message', text('account.task_added'));

        $this->assertSame('Wysłać ofertę', AccountTask::query()->sole()->name);
        $tasks = $response->json('tasks');
        $this->assertIsString($tasks);
        $this->assertMatchesRegularExpression('~^\s*<section class="ss-tasks".*?<span class="ss-task__name">Wysłać ofertę</span>.*</section>\s*$~s', $tasks);
        $this->postJson(self::TASKS, ['command' => 'todo', 'text' => ''])->assertUnprocessable();
    }

    public function test_guest_cannot_add_tasks(): void
    {
        $this->post(self::TASKS, ['command' => 'todo', 'text' => 'Zadanie'])->assertRedirect('/alpha/signin');
        $this->assertSame(0, AccountTask::query()->count());
    }
}
