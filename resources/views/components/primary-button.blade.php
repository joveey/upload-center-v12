<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#0057b7] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#004a99] focus:bg-[#004a99] active:bg-[#003b7a] focus:outline-none focus:ring-2 focus:ring-[#0057b7] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
