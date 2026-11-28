<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance | NIS-REDAS</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/nis.png') }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, rgba(0, 48, 24, .82), rgba(15, 23, 42, .86)),
                        url('{{ asset('assets/images/nis-hq-bg.jpg') }}') center/cover no-repeat fixed;
            padding: 20px;
            color: #0f172a;
        }
        .card {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 36px 32px;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .35);
            border: 1px solid rgba(255, 255, 255, .5);
        }
        .crest {
            display: block;
            width: 68px;
            height: 68px;
            object-fit: contain;
            margin: 0 auto 14px;
        }
        .pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: .74rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 10px;
        }
        p {
            font-size: .92rem;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        .eta {
            padding: 10px 14px;
            border-radius: 10px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            font-size: .82rem;
            font-weight: 600;
            margin-bottom: 22px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 10px;
            background: #006838;
            color: #fff;
            text-decoration: none;
            font-size: .85rem;
            font-weight: 700;
        }
        .btn:hover { background: #00512b; }
        .foot {
            margin-top: 22px;
            font-size: .74rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ asset('assets/images/nis.png') }}" alt="Nigeria Immigration Service" class="crest">
        <div class="pill">Scheduled Maintenance</div>
        <h1>NIS-REDAS is Under Maintenance</h1>
        <p>{{ $maintenanceMessage ?? 'NIS-REDAS is currently undergoing scheduled system maintenance. Please check back shortly.' }}</p>

        @if(! empty($maintenanceEta))
            <div class="eta">Estimated Return: {{ $maintenanceEta }}</div>
        @endif

        <div class="foot">Nigeria Immigration Service &bull; ICT Directorate</div>
    </div>
</body>
</html>
