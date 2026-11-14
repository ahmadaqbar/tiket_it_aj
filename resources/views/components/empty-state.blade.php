@props(['message' => 'Belum ada data.', 'icon' => 'inbox'])
<div class="flex flex-col items-center justify-center px-4 py-12 text-center">
    <x-icon :name="$icon" class="h-10 w-10 text-gray-300" />
    <p class="mt-3 text-sm text-gray-500">{{ $message }}</p>
    {{ $slot }}
</div>
