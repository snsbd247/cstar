<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Test payment — C-STAR</title>
    <style>
        body { font-family: system-ui, "Hind Siliguri", sans-serif; background: #f1f5f9; margin: 0; padding: 24px; color: #0f172a; }
        .card { max-width: 380px; margin: 40px auto; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .warn { background: #fef3c7; color: #92400e; padding: 10px 12px; border-radius: 10px; font-size: 14px; }
        .amount { font-size: 32px; font-weight: 700; margin: 16px 0 4px; }
        button { width: 100%; padding: 14px; border: 0; border-radius: 10px; font-size: 16px; font-weight: 600; margin-top: 10px; cursor: pointer; }
        .pay { background: #059669; color: #fff; } .cancel { background: #e2e8f0; color: #334155; }
    </style>
</head>
<body>
<div class="card">
    <p class="warn">পরীক্ষামূলক পেমেন্ট (test gateway) — কোনো আসল টাকা কাটা হবে না। শুধু training copy-তে ব্যবহারের জন্য।</p>
    <p class="amount">৳{{ number_format((float) $payment->amount) }}</p>
    <p>{{ $payment->patient->name }} · {{ $payment->tran_id }}</p>
    <form method="post" action="{{ route('pay.test.decide', $payment->tran_id) }}">
        <button class="pay" name="decision" value="pay">পরিশোধ করুন (সফল)</button>
        <button class="cancel" name="decision" value="cancel">বাতিল করুন</button>
    </form>
</div>
</body>
</html>
