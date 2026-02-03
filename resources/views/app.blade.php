<!DOCTYPE html>
<html lang="en" data-theme="{{ Auth::check() ? Auth::user()->theme : (isset($_COOKIE['theme']) ? $_COOKIE['theme'] : (request()->cookie('theme') ?? (strpos($_SERVER['HTTP_USER_AGENT'], 'Dark') !== false ? 'dark' : 'light'))) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <meta name="description" content="GarmentsOS PRO – Garments Business Management Solution">
    <link rel="manifest" href="/manifest.json">
    <title>@yield('title', $client_company->name)</title>
    <style>
        @font-face {
            font-family: 'Calibri';
            src: url('/calibri.ttf') format('truetype'); /* For TTF */
            font-weight: normal;
            font-style: normal;
        }

        /* color theme */
        :root {
            --bg-color: #111827;
            /* Default dark theme background */
            --h-bg-color: #374151;
            --secondary-bg-color: #1f2937;
            --h-secondary-bg-color: hsl(215, 28%, 13%);
            /* Default dark theme secondary background */
            --text-color: #ffffff;
            /* Default dark theme text color */
            --secondary-text: #d1d5db;
            /* Default dark theme secondary text */
            --primary-color: #2563eb;
            --h-primary-color: #1f56cd;
            /* Default dark theme primary color */
            --bg-warning: hsl(45, 50%, 30%);
            --bg-success: hsl(130, 50%, 30%);
            --bg-error: hsl(360, 50%, 30%);
            --border-warning: hsl(45, 100%, 45%);
            --border-success: hsl(130, 100%, 45%);
            --border-error: hsl(360, 100%, 45%);
            --text-warning: hsl(45, 30%, 95%);
            --text-success: hsl(130, 30%, 95%);
            --text-error: hsl(360, 30%, 95%);

            --h-bg-warning: hsl(45, 50%, 20%);
            --h-bg-success: hsl(130, 50%, 20%);
            --h-bg-error: hsl(360, 50%, 20%);

            --danger-color: hsl(0, 65%, 51%);
            --h-danger-color: hsl(0, 65%, 41%);
            --success-color: hsl(142, 65%, 36%);
            --h-success-color: hsl(142, 65%, 26%);

            --overlay-color: rgba(0, 0, 0, 0.438);
            --glass-border-color: #ffffff;
        }

        [data-theme='light'] {
            --bg-color: #ffffff;
            --h-bg-color: #d1d3d7;
            --secondary-bg-color: #eef0f2;
            --h-secondary-bg-color: hsl(0, 0%, 96%);
            --text-color: #1f2937;
            --secondary-text: #4b5563;
            --bg-warning: hsl(45, 100%, 80%);
            --bg-success: hsl(130, 100%, 80%);
            --bg-error: hsl(360, 100%, 80%);
            --h-bg-warning: hsl(45, 100%, 75%);
            --h-bg-success: hsl(130, 100%, 75%);
            --h-bg-error: hsl(360, 100%, 75%);
            --border-warning: hsl(45, 100%, 40%);
            --border-success: hsl(130, 100%, 40%);
            --border-error: hsl(360, 100%, 40%);
            --text-warning: hsl(45, 75%, 35%);
            --text-success: hsl(130, 75%, 35%);
            --text-error: hsl(360, 75%, 35%);
            --glass-border-color: #000000;
        }

        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        .bg-\[var\(--primary-color\)\] {
            color: #e2e8f0 !important;
        }

        .bg-\[var\(--primary-color\)\] svg {
            fill: #e2e8f0 !important;
        }

        .my-scrollbar-2 {
            overflow: auto; /* ensure it's scrollable itself */
        }

        /* Now target ONLY this element's own scrollbar */
        .my-scrollbar-2::-webkit-scrollbar,
        .my-scrollbar-2::-webkit-scrollbar-track,
        .my-scrollbar-2::-webkit-scrollbar-thumb,
        .my-scrollbar-2::-webkit-scrollbar-corner {
            all: unset;
        }

        .my-scrollbar-2::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .my-scrollbar-2::-webkit-scrollbar-track {
            background: var(--secondary-bg-color);
            border-radius: 8px;
        }

        .my-scrollbar-2::-webkit-scrollbar-thumb {
            background: linear-gradient(
                180deg,
                var(--primary-color),
                var(--h-primary-color)
            );
            border-radius: 8px;
            border: 2px solid var(--secondary-bg-color);
            transition: background 0.3s ease;
        }

        .my-scrollbar-2::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(
                180deg,
                var(--h-primary-color),
                var(--primary-color)
            );
        }

        .scrollbar-hidden::-webkit-scrollbar {
            display: none !important;
        }

        .fade-in {
            animation: fadeIn 0.35s ease-in-out;
        }

        .scale-in {
            animation: scaleIn 0.4s ease-in-out;
        }

        .scale-out {
            animation: scaleOut 0.4s ease-in-out;
        }

        /* Example animation */
        @keyframes fadeIn {
            0% {
                opacity: 0;
            }

            100% {
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            0% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0.9);
            }
            60% {
                transform: scale(1.05);
            }
            80% {
                transform: scale(0.97);
            }
            100% {
                transform: scale(1);
            }
        }

        @keyframes scaleOut {
            0% {
                transform: scale(1);
            }
            30% {
                transform: scale(1.05);
            }
            60% {
                transform: scale(0.95);
            }
            100% {
                transform: scale(0);
            }
        }

        .fade-out {
            animation: fadeOut 0.35s forwards !important;
        }

        .opacity-zero {
            opacity: 0;
        }

        .opacity-transition {
            transition: opacity .2s linear;
        }

        .card {
            transition: all 0.3s ease-in-out;
            position: relative;
        }

        .card:not(.no-translate):hover {
            transform: translateY(-0.3rem);
        }

        .card:hover {
            background-color: var(--h-secondary-bg-color);
            box-shadow: 0 5px 0.8rem var(--bg-color);
        }

        .card button {
            transition: all 0.2s ease-in-out;
        }

        .card:hover button {
            scale: 1.1;
        }

        .active_inactive_dot {
            opacity: 100;
            transition: all 0.2s ease-in-out;
        }

        .active_inactive {
            opacity: 0;
            transition: all 0.2s ease-in-out;
        }

        .card:hover .active_inactive {
            opacity: 100;
        }

        .card:hover .active_inactive_dot {
            opacity: 0;
        }

        .nav-link.active {
            background-color: var(--h-bg-color) !important;
        }

        .nav-link.active i {
            color: var(--primary-color) !important;
        }

        .nav-link.active svg {
            fill: var(--primary-color) !important;
        }

        .nav-link.active:hover i {
            color: var(--h-primary-color) !important;
        }

        .nav-link.active:hover svg {
            fill: var(--h-primary-color) !important;
        }

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
            /* For Firefox */
        }

        input[disabled] {
            cursor: not-allowed;
        }

        input[readonly] {
            background-color: transparent !important;
            pointer-events: none;
        }

        select[disabled] {
            cursor: not-allowed;
        }

        input::-webkit-calendar-picker-indicator {
            display: none !important;
            -webkit-appearance: none;
        }

        strong {
            font-weight: 600 !important;
        }

        span {
            color: var(--secondary-text) !important;
        }

        .open-dropdown:hover .open-dropdown-hover\:block {
            display: block;
        }

        input.row-checkbox:checked + input {
            opacity: 1 !important;
            pointer-events: all !important;
        }

        .switchBtn {
            display: flex;
            justify-content: left;
        }

        .switchBtn .circle {
            background-color: var(--bg-color);
        }

        .switchBtn.active {
            justify-content: right;
        }

        .switchBtn.active .circle {
            background-color: var(--secondary-text);
        }

        .selectParent:has(input:focus) .selectDropdownIcon {
            scale: 1 -1;
        }
    </style>

    @vite('resources/css/app.css')

    @if($pusherEnabled)
        <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    @endif
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js')
            .then(reg => console.log('Service Worker registered ✔️', reg))
            .catch(err => console.warn('Service Worker registration failed ❌', err));
        }
    </script>
    <script src="{{ asset('jquery.js') }}"></script>
    <script src="{{ asset('js/validate-inputs.js') }}"></script>
    <script>
        let closeOnClickOutside;
        let escToClose;
        let enterToSubmit;

        function formatDate(date, notDay, dbDate) {
            if (!date) return '';

            const inputDate = new Date(date);

            const day = inputDate.getDate().toString().padStart(2, '0');
            const monthNum = (inputDate.getMonth() + 1).toString().padStart(2, '0'); // numeric month
            const month = inputDate.toLocaleString('en-US', { month: 'short' });
            const year = inputDate.getFullYear();
            const weekday = inputDate.toLocaleString('en-US', { weekday: 'short' });

            let formatted = `${day}-${month}-${year} ${weekday}`;
            if (notDay) {
                formatted = `${day}-${month}-${year}`;
            } else if (dbDate) {
                formatted = `${year}-${monthNum}-${day}`; // YYYY-MM-DD
            }
            return formatted;
        }

        function formatNumbersDigitLess(number) {
            number = Number(number);
            let formatted = new Intl.NumberFormat('en-US').format(number);
            return formatted;
        }

        function formatNumbersWithDigits(number, maxFraction, minFraction) {
            number = Number(number);
            let formatted = new Intl.NumberFormat('en-US', {
                maximumFractionDigits: maxFraction,
                minimumFractionDigits: minFraction
            }).format(number);
            return formatted;
        }

        @if (Auth::check())
            let menu_shortcuts = JSON.parse(@json(Auth::user()->menu_shortcuts)) || [];
            const maxShortcutsLimit = 7;
        @endif
    </script>

    <script src="{{ asset('js/components/card.js') }}"></script>
    <script src="{{ asset('js/components/modal.js') }}"></script>
    <script src="{{ asset('js/components/context-menu.js') }}"></script>
    <script src="{{ asset('js/global-filter-manager.js') }}"></script>
</head>

<body class="bg-[var(--secondary-bg-color)] text-[var(--text-color)] text-sm min-h-screen flex flex-col md:flex-row items-center justify-center fade-in" cz-shortcut-listen="true">
    {{-- side bar --}}
    @if (Auth::check())
        <script>
            const url = window.location.href; // Get the current URL
        </script>

        @component('components.sidebar')
        @endcomponent
    @endif

    <!-- Loader -->
    <div id="page-loader" class="fixed inset-0 z-[9999] bg-[var(--overlay-color)] bg-opacity-80 flex items-center justify-center hidden">
        <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>
    <div class="wrapper flex-1 flex flex-col md:h-screen relative w-full overflow-y-hidden">
        {{-- main content --}}
        <main class="flex-1 px-8 py-0 md:p-8 overflow-y-auto my-scrollbar-2 flex items-center justify-center bg-[var(--bg-color)] rounded-3xl mx-2.5 md:mr-2.5 {{ request()->is('login') ? 'mt-2.5 md:ml-2.5' : 'mt-0 md:ml-0' }} md:mt-2.5 relative">
            {{-- alert --}}
            <div id="messageBox" class="absolute top-5 mx-auto flex items-center flex-col space-y-3 z-[100] text-sm w-full select-none pointer-events-none">
                @if (session('info'))
                    <x-alert type="info" :messages="session('info')" />
                @endif

                @if (session('success'))
                    <x-alert type="success" :messages="session('success')" />
                @endif

                @if (session('warning'))
                    <x-alert type="warning" :messages="session('warning')" />
                @endif

                @if (session('error'))
                    <x-alert type="error" :messages="session('error')" />
                @endif
            </div>
            <!-- Notification Box -->
            <div id="notificationBox" class="absolute top-5 right-5 flex flex-col space-y-3 z-[100] text-sm mx-auto items-end w-full select-none">
                {{-- <x-notification
                    title="Payment Method Expiring"
                    message="Your card ending in 1122 is expiring soon. Please update your billing info."
                    actionLabel="Update Card"
                    actionUrl="/billing"
                />
                <x-notification
                    title="Payment Method Expiring"
                    message="Your card ending in 1122 is expiring soon. Please update your billing info."
                /> --}}
            </div>
            <div class="left_actions absolute top-5 left-5 flex gap-2">
                <div id="go_back_button" class="border border-gray-600 group bg-[var(--bg-color)] h-full rounded-xl cursor-pointer flex items-center justify-end p-1 overflow-hidden hover:pr-3 transition-all duration-300 ease-in-out">
                    <div class="flex items-center justify-center bg-[var(--h-bg-color)] rounded-lg p-2">
                        <svg class="size-3 transition-all duration-300 ease-in-out group-hover:size-2.5 fill-[var(--secondary-text)]"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                        <path d="M19 12H5m6-6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" fill="none"/>
                        </svg>
                    </div>
                    <span class="inline-block max-w-0 opacity-0 overflow-hidden whitespace-nowrap transition-all duration-300 ease-in-out group-hover:opacity-100 group-hover:max-w-[200px] group-hover:ml-2">
                        Go Back
                    </span>
                </div>
                <div id="refresh_button" class="border border-gray-600 group bg-[var(--bg-color)] h-full rounded-xl cursor-pointer flex items-center justify-end p-1 overflow-hidden hover:pr-3 transition-all duration-300 ease-in-out">
                    <div class="flex items-center justify-center bg-[var(--h-bg-color)] rounded-lg p-2">
                        <svg class="size-3 transition-all duration-300 ease-in-out group-hover:size-2.5 fill-[var(--secondary-text)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                            <g>
                              <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/>
                              <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z" transform="translate(0.3, 0.3)" />
                            </g>
                        </svg>
                    </div>
                    <span class="inline-block max-w-0 opacity-0 overflow-hidden whitespace-nowrap transition-all duration-300 ease-in-out group-hover:opacity-100 group-hover:max-w-[200px] group-hover:ml-2">
                        Refersh
                    </span>
                </div>
            </div>
            <div class="main-child grow">
                @yield('content')
            </div>
        </main>

        {{-- footer --}}
        @component('components.footer')
        @endcomponent
    </div>

    <script>
        let calculations = {};
        // go back and refresh script
        if (window.history.length > 1) {
            document.getElementById('go_back_button').classList.remove('hidden');
            document.getElementById('go_back_button').addEventListener('click', () => {
            window.history.back();
        })
        } else {
            document.getElementById('go_back_button').classList.add('hidden')
        }
        document.getElementById('refresh_button').addEventListener('click', () => {
            location.reload();
        })
        function checkMax(input) {
            input.value = input.value.replace(/\D/g, '');

            let errorElem = document.getElementById(input.id+"-error");

            const max = parseInt(input.max, 10);
            if (parseInt(input.value, 10) > max) {
                errorElem.textContent = `Value cannot exceed ${max}.`;
                if (errorElem.classList.contains("hidden")) {
                    errorElem.classList.remove("hidden");
                }

                input.value = max;
            } else {
                errorElem.textContent = ``;
                if (!errorElem.classList.contains("hidden")) {
                    errorElem.classList.add("hidden");
                }
            }
        }

        // Message box animation
        function messageBoxAnimation() {
            setTimeout(function() {
                // Select all alert messages by their common class
                const messages = document.querySelectorAll('.alert-message');

                messages.forEach((message) => {
                    if (message) {
                        message.classList.add('fade-out');
                        message.addEventListener('animationend', () => {
                            message.style.display = 'none'; // Hide the element after animation
                        });
                    }
                });
            }, 5000); // Trigger fade-out after 5 seconds
        }
        messageBoxAnimation();

        // notification box animation
        function hideNotification(notificationElem) {
            notificationElem.classList.add('fade-out');

            notificationElem.addEventListener('animationend', () => {
                notificationElem.style.display = 'none';
                notificationElem.remove();
            });
        }

        function openDropDown(e, trigger) {
            e.stopPropagation();

            const relatedDropDownMenu = trigger.nextElementSibling;

            if (relatedDropDownMenu.classList.contains('hidden')) {
                closeAllDropdowns(relatedDropDownMenu); // Pass the one to skip

                relatedDropDownMenu.classList.remove('hidden');
                setTimeout(() => {
                    relatedDropDownMenu.classList.add('opacity-100', "scale-in");
                    relatedDropDownMenu.classList.remove('opacity-0', "scale-out");
                }, 10);
            } else {
                relatedDropDownMenu.classList.remove('opacity-100', "scale-in");
                relatedDropDownMenu.classList.add('opacity-0', "scale-out");
                setTimeout(() => {
                    relatedDropDownMenu.classList.add('hidden');
                }, 300);
            }
        }

        function closeAllDropdowns(skipElement = null) {
            const dropdownMenus = document.querySelectorAll('.dropdownMenu');

            dropdownMenus.forEach(menu => {
                if (menu === skipElement) return; // Skip the one we want to keep open

                menu.classList.remove('opacity-100', 'scale-in');
                menu.classList.add('opacity-0', 'scale-out');
                setTimeout(() => {
                    menu.classList.add('hidden');
                }, 300);
            });
        }

        document.addEventListener('focus', function(event) {
            if (event.target.matches('input[type="date"]')) {
                event.target.showPicker(); // Trigger the date picker
            } else if (event.target.matches('input[type="month"]')) {
                event.target.showPicker(); // Trigger the date picker
            }
        }, true); // Use capturing phase

        const previewImage = (event) => {
            const file = event.target.files[0];
            const placeholderIcon = document.querySelector(".placeholder_icon");
            const uploadText = document.querySelector(".upload_text");

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    placeholderIcon.src = e.target.result;
                    placeholderIcon.classList.add("rounded-md", "w-full", "h-auto");
                    uploadText.textContent = "Preview";
                }
                reader.readAsDataURL(file);
            }
        }

        const previewFileName = (event) => {
            const file = event.target.files[0];
            const uploadText = document.querySelector(".upload_text");

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    uploadText.textContent = `Selected: ${file.name}`;
                }
                reader.readAsDataURL(file);
            }
        }

        document.addEventListener("contextmenu", e => e.preventDefault());

        // Function to send AJAX request to update last_activity
        function updateLastActivity() {
            $.ajax({
                url: '/update-last-activity',
                type: 'POST',
                data: {}, // Optional if you want to send any data, can be left empty
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status === 'updated') {
                        console.log("Last activity updated successfully.");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Failed to update last activity", error);
                }
            });
        }

        // Call the function immediately once
        updateLastActivity();

        // Then every 60 minutes (3600000 milliseconds)
        setInterval(updateLastActivity, 60 * 60 * 1000);
    </script>

    @if($pusherEnabled)
        <script>
            var pusher = new Pusher('c99f4e2f9df04cc306f4', {
                cluster: 'ap2',
                forceTLS: true
            });

            var channel = pusher.subscribe('notifications');

            // Utility function to create and show notification
            function showNotification(title = '', message = '') {
                const notificationBox = document.getElementById("notificationBox");
                if (!notificationBox) return;

                const wrapper = document.createElement("div");
                wrapper.innerHTML = `
                    <x-notification
                        title="${title}"
                        message="${message}"
                    />
                `;
                const notificationElement = wrapper.firstElementChild;
                notificationBox.prepend(notificationElement);

                setTimeout(() => hideNotification(notificationElement), 5000);
            }

            // Listen to the event
            channel.bind('App\\Events\\NewNotificationEvent', function (data) {
                console.log('📢 Notification received:', data);

                const dataObject = data.data;

                @if(!request()->is('login') && !request()->is('subscription-expired'))
                    if ((dataObject.type === "user_inactivated" || dataObject.type === "password_reset")
                        && dataObject.id == {{Auth::user()->id}}) {

                        // Show notification immediately
                        showNotification(dataObject.title, dataObject.message);

                        // Logout after 1.5 seconds
                        setTimeout(() => {
                            document.getElementById("logoutForm").submit();
                        }, 5000);
                    }
                @endif

                @if(request()->is('orders/create'))
                    if (dataObject.title === "New Article Added.") {
                        const dateInput = document.querySelector("#date");

                        if (dateInput?.value) {
                            getDataByDate(dateInput);
                            showNotification(dataObject.title, dataObject.message);
                        }
                    }
                @endif
            });

            pusher.connection.bind('connected', function() {
                console.log('✅ Pusher connected');
            });
        </script>
    @endif


@yield('scripts')
</body>
<script>
    let doHide = false;
    window.addEventListener('beforeunload', function () {
        showLoader();
    });

    function showLoader() {
        document.getElementById('page-loader').classList.remove('hidden');
        document.getElementById('page-loader').classList.remove('fade-out');
        document.getElementById('page-loader').classList.add('fade-in');
    }

    function hideLoader() {
        document.getElementById('page-loader').classList.add('hidden');
        document.getElementById('page-loader').classList.add('fade-out');
        document.getElementById('page-loader').classList.remove('fade-in');
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Loader for normal <a> clicks
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                const target = this.getAttribute('target');

                if (
                    href &&
                    !href.startsWith('#') &&
                    !href.startsWith('javascript:') &&
                    !target
                ) {
                    showLoader();
                }
            });
        });

        // Loader for all form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function () {
                showLoader();
            });
        });
    });

    // Hide loader on full page load (in case of back/refresh)
    window.addEventListener('load', function () {
        hideLoader();
    });

    // ======================
    // For AJAX Requests
    // ======================

    // If using Axios
    if (typeof axios !== 'undefined') {
        axios.interceptors.request.use(config => {
            showLoader();
            return config;
        }, error => {
            hideLoader();
            return Promise.reject(error);

        });

        axios.interceptors.response.use(response => {
            hideLoader();
            return response;
        }, error => {
            hideLoader();
            return Promise.reject(error);
        });
    }

    // If using jQuery
    if (typeof $ !== 'undefined') {
        $(document).ajaxStart(function () {
            showLoader();
        }).ajaxStop(function () {
            if (!doHide) {
                hideLoader();
            }
            doHide = false;
        });
    }

    // its for cache clear
    // Unregister service worker and clear caches
    // if ('serviceWorker' in navigator) {
    // navigator.serviceWorker.getRegistrations().then(registrations => {
    //     for (let registration of registrations) {
    //     registration.unregister();
    //     }
    // });

    // caches.keys().then(function(names) {
    //     for (let name of names) {
    //     caches.delete(name);
    //     }
    // }).then(() => {
    //     console.log('Service Worker and Cache cleared!');
    //     window.location.reload(); // Optional: reload the page
    // });
    // }

    // const items = document.querySelectorAll(".search_container > div");
    // function filterByText(inputValue, jsonPath) {
    //     inputValue = inputValue.toLowerCase().trim();

    //     items.forEach(item => {
    //         const jsonData = item.getAttribute("data-json");
    //         if (!jsonData) return;

    //         const parsed = JSON.parse(jsonData);
    //         const fieldValue = getNestedValue(parsed, jsonPath)?.toString().toLowerCase() || "";

    //         const match = fieldValue.includes(inputValue);
    //         item.style.display = match ? "" : "none";
    //     });

    //     const noItemsError = document.getElementById("noItemsError");
    //     noItemsError.style.display = Array.from(items).every(item => item.style.display === "none") ? "block" : "none";
    // }

    // function filterBySelect(inputValue, jsonPath) {
    //     inputValue = inputValue.toLowerCase().trim();

    //     items.forEach(item => {
    //         const jsonData = item.getAttribute("data-json");
    //         if (!jsonData) return;

    //         const parsed = JSON.parse(jsonData);
    //         const fieldValue = getNestedValue(parsed, jsonPath)?.toString().toLowerCase() || "";

    //         const match = fieldValue == inputValue;
    //         item.style.display = match ? "" : "none";
    //     });

    //     const noItemsError = document.getElementById("noItemsError");
    //     noItemsError.style.display = Array.from(items).every(item => item.style.display === "none") ? "block" : "none";
    // }

    // function getNestedValue(obj, path) {
    //     return path.split('.').reduce((acc, part) => acc?.[part], obj);
    // }

    @if(request()->route()->getActionMethod() === 'index' ||request()->route()->getActionMethod() === 'summary')
        // change layout
        function changeLayout() {
            $.ajax({
                url: "{{ route('change-data-layout') }}",
                type: 'POST',
                data: {
                    layout: authLayout,
                }, // Optional if you want to send any data, can be left empty
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status === 'updated') {
                        console.log("Layout updated successfully.");
                        location.reload();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Failed to update Layout", error);
                }
            });
        }

        // const scroller = search_container.parentElement;
        // const batchSize = 50;
        // let startIndex = 0;
        // let isFetching = false;

        // function renderNextBatch() {
        //     if (startIndex >= allDataArray.length) return;

        //     const nextChunk = allDataArray;
        //     const html = nextChunk
        //         .filter(item => item.visible === true)
        //         .map(item => authLayout === 'grid' ? createCard(item) : createRow(item))
        //         .join('');
        //     search_container.insertAdjacentHTML('beforeend', html);
        //     startIndex += batchSize;
        // }

        // scroller?.addEventListener('scroll', () => {
        //     const scrollTop = scroller.scrollTop;
        //     const scrollHeight = scroller.scrollHeight;
        //     const clientHeight = scroller.clientHeight;
        //     // console.log("Scrolling");

        //     if (scrollTop + clientHeight >= scrollHeight - 100 && !isFetching) {
        //         isFetching = true;
        //         setTimeout(() => {
        //             console.log("Render Next Batch");

        //             renderNextBatch();
        //             isFetching = false;
        //         }, 100);
        //     }
        // });

        // function renderData() {
        //     if (authLayout == "grid") {
        //         tableHead.classList.add("hidden");
        //         search_container.classList = "search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 pt-4 px-2 overflow-y-auto grow my-scrollbar-2";
        //     } else {
        //         tableHead.classList.remove("hidden");
        //         search_container.classList = "search_container overflow-y-auto grow my-scrollbar-2 mx-2 mb-3";
        //     }
        //     search_container.innerHTML = "";
        //     startIndex = 0;
        //     // renderNextBatch();
        //     allDataArray.forEach(item => {
        //         if (item.visible === true) {
        //             if (authLayout == "grid") {
        //                 search_container.insertAdjacentHTML('beforeend', createCard(item));
        //             } else {
        //                 search_container.insertAdjacentHTML('beforeend', createRow(item));
        //             }
        //         }
        //     });
        // }

        // renderData(); // initial load
    @endif

    function closeModal(modalId, animate = 'animate') {
        const modal = document.getElementById(`${modalId}-wrapper`);
        const modalForm = modal.querySelector('form');

        if (animate == 'animate') {
            modalForm.classList.add('scale-out');

            modalForm.addEventListener('animationend', () => {
                modal.classList.add('fade-out');

                modal.addEventListener('animationend', () => {
                    modal.remove();
                }, { once: true });
            }, { once: true });
        } else {
            modal.remove();
        }
        document.removeEventListener('mousedown', closeOnClickOutside);
        document.removeEventListener('keydown', escToClose);
        document.removeEventListener('keydown', enterToSubmit);
    }

    function selectThisOption(optionLiElem) {
        const forId = optionLiElem.dataset.for;

        const selectSearch = document.getElementById(forId);
        const dbInput = document.querySelector(`.dbInput[data-for="${forId}"]`);

        selectSearch.value = optionLiElem.textContent.trim();
        dbInput.value = optionLiElem.dataset.value;

        // Remove 'selected' from all
        const allOptions = document.querySelectorAll(`.optionsDropdown li[data-for="${forId}"]`);
        allOptions.forEach(li => li.classList.remove('selected'));

        // Add 'selected' to current
        optionLiElem.classList.add('selected');

        // Trigger change event manually on the hidden input
        const changeEvent = new Event('change', { bubbles: true });
        dbInput.dispatchEvent(changeEvent);
    }

    function searchSelect(selectSearchInput) {
        const inputValue = selectSearchInput.value.toLowerCase().trim();
        const forId = selectSearchInput.dataset.for;

        const allOptions = document.querySelectorAll(`.optionsDropdown li[data-for="${forId}"]`);

        const isDefaultSelection = inputValue.startsWith('-- select');

        allOptions.forEach((li) => {
            const optionText = li.textContent.toLowerCase().trim();

            // Always show "-- Select Customer --"
            if (optionText.startsWith('-- select')) {
                li.classList.remove('hidden');
                li.innerHTML = li.textContent; // Remove highlight
                return;
            }

            // If default option is selected, show everything
            if (isDefaultSelection) {
                li.classList.remove('hidden');
                li.innerHTML = li.textContent; // Remove highlight
                return;
            }

            // Otherwise filter based on input value
            if (optionText.includes(inputValue) && inputValue.length > 0) {
                li.classList.remove('hidden');
                // Highlight the matching part
                const originalText = li.textContent;
                const regex = new RegExp(`(${inputValue})`, 'ig');
                li.innerHTML = originalText.replace(regex, '<mark class="bg-yellow-200 text-black rounded">$1</mark>');
            } else if (optionText.includes(inputValue)) {
                li.classList.remove('hidden');
                li.innerHTML = li.textContent; // Remove highlight if input is empty
            } else {
                li.classList.add('hidden');
                li.innerHTML = li.textContent; // Remove highlight
            }
        });
    }

    function validateSelectInput(selectSearchInput) {
        const inputValue = selectSearchInput.value.toLowerCase().trim();
        const forId = selectSearchInput.id;
        const dbInput = document.querySelector(`.dbInput[data-for="${forId}"]`);

        const allOptions = document.querySelectorAll(`.optionsDropdown li[data-for="${forId}"]`);

        let isValid = false;

        allOptions.forEach((li) => {
            const optionText = li.textContent.toLowerCase().trim();
            if (optionText === inputValue) {
                isValid = true;
            }
        });

        if (!isValid) {
            // Clear both fields if no exact match
            selectFirstOption(forId);
        }
    }

    function selectFirstOption(forId) {
        const firstOption = document.querySelector(`.optionsDropdown li[data-for="${forId}"]:not(.hidden)`);
        if (firstOption) {
            selectThisOption(firstOption);
        }
    }

    document.querySelectorAll(".selectParent .dbInput")
        .forEach(dbInput => selectFirstOption(dbInput.dataset.for));

    function selectClicked(input) {
        const searchInput = input.closest('.selectParent').querySelector('.dropDownParent input')
        searchInput.focus();
        searchInput.value = '';
        searchSelect(searchInput);

        const inputRect = input.getBoundingClientRect();
        const dropdown = input.closest(".selectParent").querySelector(".dropDownParent");

        dropdown.style.width = inputRect.width + "px";
        dropdown.style.top = (inputRect.top + inputRect.height) + "px";
        dropdown.style.left = inputRect.left + "px";
    }

    function selectKeyDown(event, input) {
        const dropdown = input.closest(".selectParent").querySelector(".optionsDropdown");
        const allOptions = dropdown.querySelectorAll("li");
        const options = Array.from(allOptions).filter(li => !li.classList.contains("hidden"));

        function scrollIntoViewIfNeeded(element) {
            if (element && typeof element.scrollIntoView === "function") {
                element.scrollIntoView({ block: "nearest", inline: "nearest" });
            }
        }

        if (event.key === "ArrowDown") {
            event.preventDefault();
            const selected = dropdown.querySelector("li.selected:not(.hidden)");
            let next = selected
                ? options[options.indexOf(selected) + 1]
                : options[0];
            if (next) {
                options.forEach(li => li.classList.remove("selected"));
                next.classList.add("selected");
                input.value = next.textContent.trim();
                scrollIntoViewIfNeeded(next);
            }
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            const selected = dropdown.querySelector("li.selected:not(.hidden)");
            let prev = selected
                ? options[options.indexOf(selected) - 1]
                : options[options.length - 1];
            if (prev) {
                options.forEach(li => li.classList.remove("selected"));
                prev.classList.add("selected");
                input.value = prev.textContent.trim();
                scrollIntoViewIfNeeded(prev);
            }
        } else if (event.key === "Enter") {
            event.preventDefault();
            const selected = dropdown.querySelector("li.selected:not(.hidden)");
            if (selected) {
                selectThisOption(selected);
                input.blur();
            }
        } else if (event.key === "Escape") {
            input.blur();
        }
    }

    function switchBtnTogggle(switchBtn) {
        if (switchBtn.classList.contains('active')) {
            switchBtn.classList.remove('active');
            updateMenuCustomization(switchBtn.dataset.for, 'not-active')
        } else {
            if (menu_shortcuts.length >= maxShortcutsLimit) {
                messageBox.innerHTML = `
                    <x-alert type="error" :messages="'You have reached the maximum limit of ${maxShortcutsLimit} shortcuts.'" />
                `;
                messageBoxAnimation();
                return null;
            }

            switchBtn.classList.add('active');
            updateMenuCustomization(switchBtn.dataset.for, 'active')
        }
    }

    function updateMenuCustomization(moduleName, newState) {
        if (newState == 'active' && !menu_shortcuts.includes(moduleName)) {
            menu_shortcuts.push(moduleName); // moduleName = 'user'
        } else {
            menu_shortcuts = menu_shortcuts.filter(item => item !== moduleName);
        }

        renderMenuShortcuts();
        reRenderInfoInModal('.menuModalInfo', `Enabled: ${menu_shortcuts.length}/${maxShortcutsLimit}`);

        $.ajax({
            url: '/update-menu-shortcuts',
            type: 'POST',
            data: {
                menu_shortcuts
            }, // Optional if you want to send any data, can be left empty
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.status === 'success') {
                    console.log("Menu shortcuts updated successfully.");
                }
            },
            error: function(xhr, status, error) {
                console.error("Menu shortcuts not updated", error);
            }
        });
    }

    function formatAllAmountInputs() {
        const allAmountInputs = document.querySelectorAll('input[type="amount"]');

        allAmountInputs.forEach((input) => {
            validateInput(input);
        });
        document.getElementById('amount-error')?.classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', () => {
        formatAllAmountInputs();
    })

    document.addEventListener("submit", function (e) {
        if (e.target.matches("form")) {   // sirf form ke liye trigger
            if (e.target.action.includes("logout")) {
                return;
            }

            if (!validateAllInputs()) {
                e.preventDefault();
                messageBox.innerHTML = `
                    <x-alert type="error" :messages="'Some fields are incorrect. Please fix them.'" />
                `;
                messageBoxAnimation();
            }
        }

        hideLoader();

        document.querySelectorAll('input[type="amount"]').forEach(input => {
            formatAmountInput(input);
        });
    });

    function formatAmountInput(input) {
        let value = input.value.replace(/[^0-9.]/g, ''); // sirf digits & dot allow karo

        if (value.includes('.')) {
            let [intPart, decPart] = value.split('.');
            decPart = decPart.slice(0, 2); // max 2 decimals
            value = decPart ? `${intPart}.${decPart}` : intPart;
        }

        input.value = value;
        input.type = 'number';
        input.step = '0.01';
    }

    function sortByThis(elem) {
        const tableHead = elem.parentElement;
        const index = Array.from(tableHead.children).indexOf(elem);
        const searchContainer = tableHead.parentElement.querySelector('.search_container');
        const rows = Array.from(searchContainer.querySelectorAll('.item'));

        searchContainer.querySelectorAll('.item').forEach((row, i) => {
            row.dataset.index = i;
        });

        // toggle order
        const order = elem.dataset.sort === "asc" ? "desc" : "asc";
        elem.dataset.sort = order;

        // helper: strict numeric test (whole string is a number)
        const isWholeNumberString = s => {
            if (!s) return false;
            const cleaned = s.replace(/,/g, '').trim();
            return /^-?\d+(\.\d+)?$/.test(cleaned);
        };

        // helper: try many date formats and return timestamp or NaN
        const parseDateString = s => {
            if (!s) return NaN;
            // remove weekday words and commas
            s = s.replace(/\b(?:mon|tue|wed|thu|fri|sat|sun|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/gi, '').replace(/,/g, '').trim();

            // try native parse (ISO and many browser-parseable formats)
            const iso = Date.parse(s);
            if (!isNaN(iso)) return iso;

            // normalize separators to spaces
            const normalized = s.replace(/[-\/]/g, ' ').replace(/\s+/g, ' ').trim();

            // month map
            const months = {
                jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
                jul: 6, aug: 7, sep: 8, sept: 8, oct: 9, nov: 10, dec: 11
            };

            // DD MMM YYYY  (e.g. "20 Sep 2025" or "20 Sep 25")
            let m = normalized.match(/^(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{2,4})$/);
            if (m) {
                const day = Number(m[1]);
                const mon = months[m[2].slice(0,3).toLowerCase()];
                const year = Number(m[3]) + (m[3].length === 2 ? 2000 : 0);
                if (mon !== undefined) return new Date(year, mon, day).getTime();
            }

            // DD MMM  (no year) -> assume current year
            m = normalized.match(/^(\d{1,2})\s+([A-Za-z]{3,9})$/);
            if (m) {
                const day = Number(m[1]);
                const mon = months[m[2].slice(0,3).toLowerCase()];
                const year = new Date().getFullYear();
                if (mon !== undefined) return new Date(year, mon, day).getTime();
            }

            // MMM DD YYYY  (e.g. "Sep 20 2025")
            m = normalized.match(/^([A-Za-z]{3,9})\s+(\d{1,2})\s+(\d{2,4})$/);
            if (m) {
                const mon = months[m[1].slice(0,3).toLowerCase()];
                const day = Number(m[2]);
                const year = Number(m[3]) + (m[3].length === 2 ? 2000 : 0);
                if (mon !== undefined) return new Date(year, mon, day).getTime();
            }

            // numeric date-ish fallback like 21-07-2025 or 21/07/2025
            m = normalized.match(/^(\d{1,2})\s+(\d{1,2})\s+(\d{2,4})$/);
            if (m) {
                const d1 = Number(m[1]), d2 = Number(m[2]), y = Number(m[3]) + (m[3].length === 2 ? 2000 : 0);
                // assume format is day month year (common in your sample)
                const day = d1;
                const month = d2 - 1;
                if (month >= 0 && month <= 11) return new Date(y, month, day).getTime();
            }

            return NaN;
        };

        // Main comparator that chooses numeric -> date -> string
        rows.sort((a, b) => {
            const aText = (a.children[index] && a.children[index].innerText) ? a.children[index].innerText.trim() : '';
            const bText = (b.children[index] && b.children[index].innerText) ? b.children[index].innerText.trim() : '';

            // 1) numeric (only if the whole string is numeric)
            if (isWholeNumberString(aText) && isWholeNumberString(bText)) {
                const na = parseFloat(aText.replace(/,/g, ''));
                const nb = parseFloat(bText.replace(/,/g, ''));
                return order === "asc" ? na - nb : nb - na;
            }

            // 2) date parsing
            const ta = parseDateString(aText);
            const tb = parseDateString(bText);
            if (!isNaN(ta) && !isNaN(tb)) {
                return order === "asc" ? ta - tb : tb - ta;
            }

            // 3) fallback to locale string compare (numeric option helps e.g. "10" vs "2")
            return order === "asc"
                ? aText.localeCompare(bText, undefined, { numeric: true, sensitivity: 'base' })
                : bText.localeCompare(aText, undefined, { numeric: true, sensitivity: 'base' });
        });

        // re-render
        searchContainer.innerHTML = "";
        rows.forEach(row => searchContainer.appendChild(row));
    }

    function resetSort() {
        const searchContainer = document.querySelector('.search_container');
        const rows = Array.from(searchContainer.querySelectorAll('.item'));

        rows.sort((a, b) => {
            return a.dataset.index - b.dataset.index;
        });

        searchContainer.innerHTML = "";
        rows.forEach(row => searchContainer.appendChild(row));

        // Clear sort state from all headers
        document.querySelectorAll('#table-head > div').forEach(header => {
            delete header.dataset.sort;
        });
    }

    // function printPage() {
    //     // get card_container and print
    //     const printContent = document.querySelector('.container-parent').innerHTML;
    //     const originalContent = document.body.innerHTML;
    //     document.body.innerHTML = printContent;
    //     window.print();
    //     document.body.innerHTML = originalContent;
    // }

    function printPage() {
        const preview = document.querySelector('.container-parent');

        let clone = preview.cloneNode(true);

        let oldIframe = document.getElementById('printIframe');
        if (oldIframe) {
            oldIframe.remove();
        }

        // Naya iframe banao
        let printIframe = document.createElement('iframe');
        printIframe.id = "printIframe";
        printIframe.style.position = "absolute";
        printIframe.style.width = "0px";
        printIframe.style.height = "0px";
        printIframe.style.border = "none";
        printIframe.style.display = "none";

        document.body.appendChild(printIframe);

        let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
        printDocument.open();

        const headContent = document.head.innerHTML;

        clone.querySelector('#calc-bottom')?.remove();

        function generatePrintBody(clone) {
            const header = clone.querySelector('#table-head');
            const body = clone.querySelector('.search_container'); // ✅ main rows container
            if (!header || !body) return clone.innerHTML;

            // Clean unnecessary classes
            body.innerHTML = body.innerHTML
                .replaceAll('fade-in', '')
                .replaceAll('my-scrollbar-2', 'scrollbar-hidden');

            // Get all rows and remove `data-json` attribute
            const rows = Array.from(body.children).map(r => {
                const rowClone = r.cloneNode(true);
                rowClone.removeAttribute('data-json'); // ✅ remove if exists
                return rowClone;
            });

            const headerHTML = header.outerHTML.replace('mt-4', 'text-center');

            let html = '';
            let currentRows = [];
            let height = 0;
            const maxHeight = 840; // ~A4 landscape height

            rows.forEach((r, i) => {
                currentRows.push(r.outerHTML);
                height += r.scrollHeight || 40;

                // If height exceeds limit or last row reached
                if (height >= maxHeight || i === rows.length - 1) {
                    html += `
                        <div class="print-page flex flex-col min-h-[750px]">
                            <div class="px-4 w-full flex justify-between text-[12px] font-medium tracking-wide leading-none mb-2">
                                <div class="capitalize">${ document.getElementById('page-name').textContent } | {{ $client_company->name }}</div>
                                <div>Printed on: ${formatDate(new Date())}</div>
                            </div>
                            ${headerHTML}
                            <div class="rows px-4 text-center">
                                ${currentRows.join('')}
                            </div>
                            <div class="grow">
                            </div>
                            <div class="px-4 w-full grid grid-cols-3 text-[12px] tracking-wide leading-none mt-3">
                                <div class="text-left">
                                    Showing ${i + 1} of ${rows.length} Records
                                </div>
                                <div class="text-center">
                                    Powered by: <strong>SparkPair</strong>
                                </div>
                                <div class="text-right">
                                    Page ${Math.ceil((i + 1) / (maxHeight / 40))} of ${Math.ceil(rows.length / (maxHeight / 40))}
                                </div>
                            </div>
                        </div>
                    `;
                    if (i !== rows.length - 1)
                        html += `<div style="page-break-after:always"></div>`;

                    // Reset for next batch
                    currentRows = [];
                    height = 0;
                }
            });

            return html;
        }

        printDocument.write(`
            <html>
                <head>
                    <title>Print Statement</title>
                    ${headContent}
                    <style>
                        @page {
                            size: A4 landscape;
                            margin: 16px;
                        }

                        body {
                            margin: 0;
                            padding: 0;
                            background: #fff;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }

                        /* ✅ Make all major containers flow naturally in print */
                        .container-parent,
                        .card_container {
                            display: block !important;
                            overflow: visible !important;
                            height: auto !important;
                        }

                        /* ✅ Allow automatic page breaks */
                        * {
                            page-break-inside: auto;
                        }

                        /* ✅ Prevent rows, headers, etc. from splitting mid-page */
                        .row,
                        .record,
                        tr,
                        .card {
                            page-break-inside: avoid;
                            break-inside: avoid;
                        }

                        /* ✅ Repeat header section if you have it in a table */
                        thead {
                            display: table-header-group;
                        }

                        /* ✅ Hide scrollbars for printed view */
                        .scrollbar-hidden {
                            overflow: visible !important;
                        }

                        /* ✅ Header styling */
                        body #table-head {
                            color: white !important;
                            background: var(--primary-color) !important;
                            font-size: 10px !important;
                        }

                        body span {
                            color: black !important;
                            font-size: 10px !important;
                        }
                    </style>
                </head>
                <body>
                    ${generatePrintBody(clone)}
                </body>
            </html>
        `);

        printDocument.close();

        // Print jab iframe load ho jaye
        printIframe.onload = () => {
            printIframe.contentWindow.focus();
            printIframe.contentWindow.print();
        };
    }

    // function backupDB() {
    //     window.location.href = '/backup-db';
    // }

    async function backupDB() {
        try {
            // Ask user to select a folder
            const handle = await window.showDirectoryPicker();

            // Create a file inside that folder (client-side only)
            const fileName = 'database_backup_' + new Date().toISOString().replace(/[:.]/g, '-') + '.sqlite';

            // Download the backup from Laravel route
            const response = await fetch('/backup-db');
            const blob = await response.blob();

            // Save the downloaded file inside selected folder
            const fileHandle = await handle.getFileHandle(fileName, { create: true });
            const writable = await fileHandle.createWritable();
            await writable.write(blob);
            await writable.close();

            alert('✅ Backup saved to selected folder: ' + handle.name);
        } catch (err) {
            console.error(err);
            alert('❌ Backup cancelled or failed.');
        }
    }
</script>

</html>
