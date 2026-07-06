@props(['branch' => null])

<x-form-input name="name" :label="__('org.branches.name')" :value="$branch->name ?? ''" required />
<x-form-input name="address" :label="__('org.branches.address')" :value="$branch->address ?? ''" />
<x-form-input name="city" :label="__('org.branches.city')" :value="$branch->city ?? ''" />

<label class="flex items-center gap-2 text-sm text-body-text">
    <input
        type="checkbox"
        name="is_active"
        value="1"
        @checked(old('is_active', $branch->is_active ?? true))
        class="rounded border-border-muted text-primary focus:ring-primary"
    >
    {{ __('org.branches.is_active') }}
</label>
