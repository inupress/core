<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Installed plugins</x-slot>
        <x-slot name="description">
            Plugins are Composer packages. Install with <code>composer require</code>, remove with <code>composer remove</code>.
        </x-slot>

        @if ($this->getPlugins() === [])
            <p>No plugins installed.</p>
        @else
            <table class="w-full text-start text-sm">
                <thead>
                    <tr>
                        <th class="py-2 text-start">Plugin</th>
                        <th class="py-2 text-start">Version</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->getPlugins() as $plugin)
                        <tr wire:key="{{ $plugin->id }}">
                            <td class="py-2">{{ $plugin->id }}</td>
                            <td class="py-2">{{ $plugin->version }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
