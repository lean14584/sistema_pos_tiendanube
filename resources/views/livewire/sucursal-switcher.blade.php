<div>
    <label class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1">Sucursal activa</label>
    <select
        wire:model.live="sucursalId"
        class="w-full rounded-lg border border-white/10 bg-slate-800 text-slate-200 px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"
    >
        @foreach ($sucursales as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
        @endforeach
    </select>
</div>
