<x-admin-layout title="Instruction Steps">
    <div class="page-header">
        <div>
            <p class="eyebrow">Guest experience</p>
            <h1 class="page-title">Instruction Steps</h1>
            <p class="page-subtitle">Choose a property to manage its check-in and check-out steps.</p>
        </div>
    </div>

    <div class="card card-pad mb-5">
        <form class="grid gap-3 sm:grid-cols-[1fr_auto]"><input name="search" value="{{ request('search') }}" placeholder="Search by property, city, or address" class="input mt-0"><button class="btn-secondary">Search</button></form>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($properties as $property)
            <article class="card overflow-hidden">
                <div class="h-44 bg-cover bg-center" style="background-image:url('{{ $property->heroImageUrl() }}')"></div>
                <div class="card-pad">
                    <div class="flex items-start justify-between gap-3"><h2 class="text-lg font-semibold text-slate-950">{{ $property->name }}</h2><span class="badge {{ $property->active ? 'badge-active' : 'badge-inactive' }}">{{ $property->active ? 'Active' : 'Inactive' }}</span></div>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $property->fullAddress() }}</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('admin.instructions.show', $property) }}" class="btn-secondary">Manage Steps</a>
                    </div>
                </div>
            </article>
        @empty
            <div class="card card-pad text-center text-slate-500 md:col-span-2 xl:col-span-3">No properties yet.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $properties->links() }}</div>
</x-admin-layout>
