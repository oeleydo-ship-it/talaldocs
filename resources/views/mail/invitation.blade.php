<x-mail::message>
# Workspace invitation

You have been invited to join a documentation workspace on {{ config('app.name', 'Docs') }}.

<x-mail::button :url="$url">
Accept invitation
</x-mail::button>

This link expires in 14 days.
</x-mail::message>
