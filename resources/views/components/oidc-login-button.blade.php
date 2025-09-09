@php
  $auto = $oidcConfig['auto_launch'] ?? false;
@endphp
<a href="{{ route('oidc.redirect') }}"
   class="btn btn-primary d-inline-flex align-items-center">
  <i class="{{ $oidcConfig['button_icon'] ?? 'fa-solid fa-key' }}" aria-hidden="true"></i>
  <span class="ms-2">{{ $oidcConfig['button_text'] ?? 'Login with OIDC' }}</span>
</a>
@if($auto)
<script>
  // tiny auto-launch if enabled (only when user is on login page)
  if (!sessionStorage.getItem('oidc_auto_launched')) {
    sessionStorage.setItem('oidc_auto_launched', '1');
    location.href = "{{ route('oidc.redirect') }}";
  }
</script>
@endif
