<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white border border-[#c7d9f3] rounded-md font-semibold text-xs text-[#0f172a] uppercase tracking-widest shadow-sm hover:bg-[#eef4fc] focus:outline-none focus:ring-2 focus:ring-[#0057b7] focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
