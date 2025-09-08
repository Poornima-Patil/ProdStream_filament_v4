<x-filament-panels::page>
  
<div class="space-y-6">
        <!-- Display User Information -->
        <div class="bg-white p-4 rounded-xl border-2 border-gray-200">
            <h3 class="text-lg font-semibold">Profile Information</h3>
            <p class="text-sm text-gray-500">Here are your account details:</p>
            <div class="mt-4">
                <p><span class="text-sm font-semibold"> Name: </span> <span class="text-sm"> {{ $this->getUser()->first_name }} {{$this->getUser()->last_name }}</span></p>
                <p><span class="text-sm font-semibold"> Email: </span> <span class="text-sm"> {{ $this->getUser()->email }} </span></p>
            </div>
        </div>
<x-filament-panels::form wire:submit="updatePassword"> 
    {{ $this->editPasswordForm }}

    <x-filament-panels::form.actions
        :actions="$this->getUpdatePasswordFormActions()"
    />
</x-filament-panels::form>

</x-filament-panels::page>