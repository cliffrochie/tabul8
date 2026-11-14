<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Judge Tent Cards — {{ $event->title }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 24px; }
    .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 18px; }
    .header h1 { margin: 0; font-size: 18px; }
    .header p { margin: 2px 0 0; font-size: 11px; color: #475569; }
    .card { border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 14px 18px; margin-bottom: 14px; page-break-inside: avoid; }
    .card-top { display: flex; justify-content: space-between; align-items: baseline; }
    .judge-num { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #4338ca; }
    .chair { font-size: 10px; font-weight: bold; color: #b45309; background: #fef3c7; padding: 2px 6px; border-radius: 4px; }
    .name { font-size: 16px; font-weight: bold; margin: 4px 0 2px; }
    .role { font-size: 11px; color: #475569; margin: 0 0 10px; }
    .pin-box { background: #f8fafc; border: 1px dashed #64748b; padding: 8px 12px; display: inline-block; }
    .pin-label { font-size: 9px; text-transform: uppercase; color: #64748b; display: block; }
    .pin-code { font-size: 20px; font-weight: bold; letter-spacing: 0.2em; color: #0f172a; }
    .footer { font-size: 9px; color: #64748b; margin-top: 8px; }
  </style>
</head>
<body>
  <div class="header">
    <h1>{{ $event->title }} — Judge Access Tent Cards</h1>
    <p>Event #{{ $event->id }} · Generated {{ $generatedAt }} · Keep PINs confidential</p>
  </div>

  @foreach ($judges as $judge)
    <div class="card">
      <div class="card-top">
        <span class="judge-num">Judge #{{ $judge->judge_number }}</span>
        @if ($judge->is_chairman)
          <span class="chair">PANEL CHAIRMAN</span>
        @endif
      </div>
      <div class="name">{{ $judge->name }}</div>
      <div class="role">{{ $judge->role_title }}</div>
      <div class="pin-box">
        <span class="pin-label">6-Digit Tablet Access PIN</span>
        <span class="pin-code">{{ $judge->plain_pin_reference }}</span>
      </div>
    </div>
  @endforeach
</body>
</html>
