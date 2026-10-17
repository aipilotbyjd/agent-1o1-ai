@props(['bag' => 'default'])

@if ($errors->getBag($bag)->any())
    <div {{ $attributes->class(['rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-600/15']) }}>
        <p class="font-medium">Please fix the following:</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5">
            @foreach ($errors->getBag($bag)->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
