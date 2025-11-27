@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-[#0057b7] text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-[#004a99] transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-[#0f172a] hover:border-[#c7d9f3] focus:outline-none focus:text-[#0f172a] focus:border-[#c7d9f3] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
