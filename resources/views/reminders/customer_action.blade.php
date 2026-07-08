<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Later — Manage Your Reminder</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f7f9fc;
            --card-bg: #ffffff;
            --primary: #000000;
            --text-color: #333333;
            --text-muted: #666666;
            --success: #137333;
            --success-bg: #e6f4ea;
            --error: #c5221f;
            --error-bg: #fce8e6;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 460px;
            padding: 20px;
            box-sizing: border-box;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            padding: 40px 30px;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.02);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 30px;
            color: var(--primary);
        }

        .logo span {
            font-weight: 300;
            color: var(--text-muted);
        }

        h2 {
            margin-top: 0;
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 30px;
        }

        /* Success & Error styles */
        .icon {
            font-size: 3rem;
            margin-bottom: 20px;
            display: inline-block;
        }

        .alert-box {
            padding: 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 25px;
            font-weight: 500;
            text-align: left;
        }

        .alert-box.success {
            background-color: var(--success-bg);
            color: var(--success);
            border: 1px solid rgba(19, 115, 51, 0.2);
        }

        .alert-box.error {
            background-color: var(--error-bg);
            color: var(--error);
            border: 1px solid rgba(197, 34, 31, 0.2);
        }

        /* Form styling */
        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        input[type="datetime-local"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="datetime-local"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
            outline: none;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            background-color: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s, transform 0.1s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid #ddd;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background-color: #fafafa;
            color: var(--primary);
        }

        .product-preview {
            display: flex;
            align-items: center;
            gap: 15px;
            background-color: #fcfcfc;
            border: 1px solid #f0f0f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            text-align: left;
        }

        .product-preview img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            background-color: #eee;
        }

        .product-info h4 {
            margin: 0 0 4px 0;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .product-info span {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">Buy<span>Later</span></div>

            @if($type === 'success')
                <div class="icon">✅</div>
                <h2>Success!</h2>
                <div class="alert-box success">
                    {{ $message }}
                </div>
                <p>You can close this tab now.</p>
            @elseif($type === 'error')
                <div class="icon">❌</div>
                <h2>Oops!</h2>
                <div class="alert-box error">
                    {{ $message }}
                </div>
                <p>If you need assistance, please contact the store owner.</p>
            @elseif($type === 'reschedule')
                <h2>⏰ Reschedule Reminder</h2>
                <p>{{ $message }}</p>

                <div class="product-preview">
                    @if($reminder->product_image)
                        <img src="{{ $reminder->product_image }}" alt="{{ $reminder->product_title }}">
                    @endif
                    <div class="product-info">
                        <h4>{{ $reminder->product_title }}</h4>
                        <span>Price: {{ $reminder->product_price }}</span>
                    </div>
                </div>

                <form id="reschedule-form" action="{{ route('reminders.reschedule', $reminder->token) }}" method="POST">
                    @csrf
                    <input type="hidden" id="scheduled_at_utc" name="scheduled_at_utc">
                    <div class="form-group">
                        <label for="scheduled_at">New Date & Time</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at" required>
                    </div>
                    <button type="submit" class="btn">Reschedule Now</button>
                    <a href="{{ route('reminders.cancel', $reminder->token) }}" class="btn btn-secondary">Cancel Reminder Instead</a>
                </form>

                <script>
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    document.getElementById('scheduled_at').min = `${year}-${month}-${day}T${hours}:${minutes}`;

                    document.getElementById('reschedule-form').addEventListener('submit', function(e) {
                        const localVal = document.getElementById('scheduled_at').value;
                        if (localVal) {
                            const dateObj = new Date(localVal);
                            if (!isNaN(dateObj.getTime())) {
                                document.getElementById('scheduled_at_utc').value = dateObj.toISOString();
                            }
                        }
                    });
                </script>
            @endif
        </div>
    </div>
</body>
</html>
