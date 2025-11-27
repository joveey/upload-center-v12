@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-[#0057b7] focus:ring-[#0057b7] rounded-md shadow-sm']) }}>
