<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Language Learning Service</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body class="antialiased">
    <div id="app" class="min-h-screen flex flex-col items-center justify-center bg-gray-100">
        <h1 class="text-4xl font-bold mb-6 text-indigo-600">
            Thank you for using the most amazing language learning service ever!!!
        </h1>

        @auth
            <p>Welcome back, {{ Auth::user()->name }}!</p>
            <a href="{{ route('playlists.index') }}" class="mt-4 inline-block bg-indigo-500 text-white px-6 py-2 rounded hover:bg-indigo-600">
                View Your Playlists
            </a>
        @else
            <div class="flex space-x-4 mt-8">
                <a href="{{ route('login') }}"
                   class="bg-indigo-500 text-white px-6 py-2 rounded hover:bg-indigo-600">Login</a>

                <a href="{{ route('register') }}"
                   class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">Register</a>
            </div>
        @endauth
    </div>
</body>
</html>
