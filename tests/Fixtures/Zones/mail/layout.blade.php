{{-- Layout testowy strefy mailing: znacznik, kolor akcentu z palety strefy i logo jako data URI albo CID. --}}
<html><body data-layout="mailing" data-accent="{{ $mailPalette->accent }}"><img src="{{ isset($message) ? $message->embedData($mailLogo->contents, $mailLogo->name, $mailLogo->mimeType) : $mailLogo->dataUri() }}" alt="">@yield('content')</body></html>
