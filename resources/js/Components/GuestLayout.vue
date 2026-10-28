<template>
    <div class="flex min-h-full flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl font-black text-white shadow-lg shadow-brand-600/30">
                    E
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">ERP Portal</h1>
                <p class="mt-1 text-sm text-slate-500">Inventory · Sales · Purchasing · Accounting</p>
            </div>

            <div class="rounded-2xl bg-white px-6 py-8 shadow-xl ring-1 ring-slate-200">
                <slot />
            </div>
        </div>
    </div>
</template>