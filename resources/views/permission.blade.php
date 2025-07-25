<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ $eventimage }}">
    <title>{{ $eventname }} - Permission To Install</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #121212;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: filter 0.3s ease;
            position: relative;
        }

        .background-blur {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.5);
            z-index: 0;
        }

        .modal {
            position: fixed;
            top: 5%;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(to bottom, #222, #1a1a1a);
            padding: 25px;
            width: 90%;
            max-width: 420px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
            z-index: 1000;
            text-align: center;
            color: #f0f0f0;
            border: 1px solid #444;
        }

        .modal img {
            width: 75px !important;
            height: 75px !important;
            max-width: 75px !important;
            max-height: 75px !important;
            object-fit: contain;
            display: block;
            margin: 0 auto 15px auto;
            border-radius: 8px !important;
        }

        .modal h2 {
            margin-bottom: 15px;
            color: #ffffff;
            font-size: 20px;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
        }

        .modal p {
            font-size: 14px;
            margin-bottom: 15px;
            text-align: left;
            color: #ccc;
        }

        .modal ul {
            text-align: left;
            margin-bottom: 15px;
            padding-left: 20px;
        }

        .modal li {
            font-size: 14px;
            color: #bbb;
            margin-bottom: 5px;
        }

        .button-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 15px;
        }

        .install-btn {
            padding: 12px 18px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 8px;
            transition: 0.2s;
            font-weight: bold;
            background: #666;
            color: #fff;
            border: 1px solid #888;
            box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.1);
            text-decoration: none;
        }

        .install-btn:hover {
            background: #777;
        }

        .install-btn:active,
        .install-btn.pressed {
            background: #555;
        }

        .back-btn {
            background: transparent;
            color: #bbb;
            border: none;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 5px;
            font-size: 14px;
            margin-top: 10px;
            cursor: pointer;
            width: 100%;
        }

        .back-btn:hover {
            color: #ddd;
        }

        .back-btn::before {
            content: "←";
            font-size: 16px;
        }
    </style>
</head>
<body>

    <div class="background-blur"></div>

    <div class="modal" id="permissionModal">
        <img src="{{ $eventimage }}" alt="App Icon" />

        <h2>{{ $eventname }} Needs Your Permission</h2>
        <p><b>{{ $eventname }}</b> is a <b>Progressive Web App (PWA)</b> — a web-based app that works like a regular app but installs directly from your browser. It saves data on your device so you can use the app offline and load it faster.</p>
        
        <p><strong>What is stored on your device?</strong></p>
        <ul>
            <li><strong>App Files</strong> – The app itself is stored for quick access.</li>
            <li><strong>Cached Data</strong> – Loads content faster and works offline.</li>
            <li><strong>Local Storage & IndexedDB</strong> – Saves settings and app data.</li>
        </ul>

        <p><strong>Optional Access:</strong></p>
        <ul>
            <li><strong>Location</strong> – For location-based features.</li>
            <li><strong>Device Motion</strong> – For navigation or motion-based controls.</li>
        </ul>

        <p>For the best experience, once installed, add the app to your home screen. You can uninstall it anytime from the app’s settings or by clearing your browser data.</p>
        
        <div class="button-container">
            <button class="install-btn" id="installBtn">Install</button>
            <button class="back-btn" onclick="goBack()">Back</button>
        </div>
    </div>

    <script>
        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            }
        }

        const installBtn = document.getElementById("installBtn");

        installBtn.addEventListener("mousedown", () => {
            installBtn.classList.add("pressed");
        });

        installBtn.addEventListener("mouseup", () => {
            installBtn.classList.remove("pressed");
        });

        installBtn.addEventListener("touchstart", () => {
            installBtn.classList.add("pressed");
        });

        installBtn.addEventListener("touchend", () => {
            installBtn.classList.remove("pressed");
        });

        installBtn.addEventListener("click", async () => {
            try {
                const response = await fetch('https://www.evaria.io/public/api/install', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        event_id: {{ $id }}
                    })
                });

                if (!response.ok) {
                    throw new Error('API request failed');
                }

                const result = await response.json();
                console.log('Success:', result);
                window.location.href = "{!! $url !!}";
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to call install API.');
            }
        });
    </script>

</body>
</html>
