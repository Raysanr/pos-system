<x-guest-layout>
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-900 font-display" style="font-family:'Space Grotesk',sans-serif;">Welcome back</h2>
        <p class="mt-1 text-sm text-gray-500">Sign in to SH Customer's Analytics</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-xs font-semibold text-gray-700 mb-1.5">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="block w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 transition-shadow"
                style="outline:none;"
                onfocus="this.style.borderColor='#F5A623';this.style.boxShadow='0 0 0 3px rgba(245,166,35,0.15)';"
                onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='';"
                placeholder="you@example.com">
            @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-xs font-semibold text-gray-700">Password</label>
                @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-xs font-medium" style="color:#F5A623;" onmouseover="this.style.color='#D4891A';" onmouseout="this.style.color='#F5A623';">Forgot password?</a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="block w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 transition-shadow"
                style="outline:none;"
                onfocus="this.style.borderColor='#F5A623';this.style.boxShadow='0 0 0 3px rgba(245,166,35,0.15)';"
                onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow='';"
                placeholder="••••••••">
            @error('password')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                class="w-4 h-4 rounded border-gray-300"
                style="accent-color:#F5A623;">
            <label for="remember_me" class="text-sm text-gray-600">Remember me</label>
        </div>

        <button type="submit"
            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold rounded-lg transition-all duration-150 cursor-pointer font-display"
            style="background:#0F172A; color:#F5A623; letter-spacing:0.025em;"
            onmouseover="this.style.background='#F5A623';this.style.color='#0F172A';"
            onmouseout="this.style.background='#0F172A';this.style.color='#F5A623';">
            Sign In
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Don't have an account?
        <a href="{{ route('register') }}" class="font-semibold" style="color:#F5A623;" onmouseover="this.style.color='#D4891A';" onmouseout="this.style.color='#F5A623';">Create one</a>
    </p>
</x-guest-layout>
