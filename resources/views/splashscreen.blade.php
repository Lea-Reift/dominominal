<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dominominal - Cargando</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-950 antialiased" style="margin: 0; padding: 0; overflow: hidden; width: 180px; height: 240px; position: relative;">
    <!-- Absolutely centered content -->
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center">
        <!-- Logo Section -->
        <div class="relative inline-block mb-4">
            <div class="absolute inset-0 bg-blue-600/20 rounded-full animate-ping"></div>
            <div class="relative bg-blue-600 rounded-full p-3 flex items-center justify-center">
                <svg class="h-7 w-7 text-white" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L13.09 8.26L20 9L13.09 9.74L12 16L10.91 9.74L4 9L10.91 8.26L12 2Z"/>
                </svg>
            </div>
        </div>

        <!-- Brand Section -->
        <div class="mb-4">
            <h1 class="text-xl font-bold text-gray-950 leading-tight mb-1">
                Dominominal
            </h1>
            <p class="text-sm text-gray-600">
                Sistema de Gestión
            </p>
        </div>

        <!-- Loading Section -->
        <div class="flex flex-col items-center">
            <div class="flex space-x-1.5 mb-3">
                <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
            </div>
            
            <p class="text-sm text-gray-500 font-medium">
                Cargando...
            </p>
        </div>
    </div>
</body>
</html>