@props(['user', 'action', 'label' => 'Profile Photo'])
@php($maxBytes = \App\Http\Requests\UpdateProfilePhotoRequest::MAX_KILOBYTES * 1024)

<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
      x-data="{
          preview: null,
          error: '',
          pick(event) {
              const file = event.target.files[0];
              this.error = '';
              this.preview = null;
              if (!file) return;
              if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                  this.error = 'The photo must be a JPG, PNG, or WebP image.';
              } else if (file.size > {{ $maxBytes }}) {
                  this.error = 'The photo must be 2 MB or smaller. This one is ' + (file.size / 1048576).toFixed(1) + ' MB.';
              }
              if (this.error) { event.target.value = ''; return; }
              const url = URL.createObjectURL(file);
              const probe = new Image();
              probe.onload = () => { this.preview = url; };
              probe.onerror = () => { this.error = 'That file could not be read as an image. It may be corrupted — try a different photo.'; event.target.value = ''; URL.revokeObjectURL(url); };
              probe.src = url;
          },
      }"
      {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @csrf
    @method('PUT')

    <p class="block text-sm font-medium">{{ $label }}</p>

    <div class="flex items-center gap-4">
        <template x-if="preview">
            <img :src="preview" alt="New photo preview" class="h-20 w-20 shrink-0 rounded-full object-cover ring-2 ring-accent">
        </template>
        <div x-show="!preview">
            <x-avatar :user="$user" size="h-20 w-20 text-xl" />
        </div>

        <div class="min-w-0 space-y-1">
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required @change="pick($event)"
                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200">
            <p class="text-xs text-gray-500">JPG, PNG, or WebP · up to 2 MB</p>
        </div>
    </div>

    <p x-show="error" x-text="error" x-cloak class="text-sm text-red-600"></p>
    @error('photo')
        <p x-show="!error" class="text-sm text-red-600">{{ $message }}</p>
    @enderror

    <button :disabled="!preview" class="bg-navy text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-accent transition disabled:opacity-50 disabled:cursor-not-allowed">
        {{ $user->profile_photo_path ? 'Replace Photo' : 'Upload Photo' }}
    </button>
</form>
