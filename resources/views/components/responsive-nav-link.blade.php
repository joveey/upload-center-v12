@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[#0057b7] text-start text-base font-medium text-[#004a99] bg-[#e8f1fb] focus:outline-none focus:text-[#003b7a] focus:bg-[#d8e7f7] focus:border-[#004a99] transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-[#0f172a] hover:bg-[#eef4fc] hover:border-[#c7d9f3] focus:outline-none focus:text-[#0f172a] focus:bg-[#eef4fc] focus:border-[#c7d9f3] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
