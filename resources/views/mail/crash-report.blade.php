<x-mail::message>
# {{ $level }}: {{ $title }}

**{{ $appName }} — {{ $env }}**

<x-mail::panel>
{{ $message }}
</x-mail::panel>

<x-mail::table>
| Item | Details |
| :--- | :--- |
| **Occurrences** | {{ $occurrences }} |
| **Mode** | {{ $mode }} |
| **First seen** | {{ $firstSeen }} |
| **Last seen** | {{ $lastSeen }} |
@if($userId)| **User ID** | {{ $userId }} |
@endif
@if($userEmail)| **User Email** | {{ $userEmail }} |
@endif
@if($ip)| **IP** | {{ $ip }} |
@endif
@if($route)| **Route** | {{ $route }} |
@endif
</x-mail::table>

@if($trace)
## Trace
<x-mail::panel>
```
{{ $trace }}
```
</x-mail::panel>
@endif

@if(!empty($context))
## Context
<x-mail::panel>
```json
{!! $context !!}
```
</x-mail::panel>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
