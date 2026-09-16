<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout Courier Comparison Demo</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; padding: 2rem; color: #1e293b; }
        .card { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .rate-option { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.75rem; cursor: pointer; }
        .rate-option.recommended { border-color: #10b981; background: #ecfdf5; }
        .badge { background: #10b981; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .price { font-size: 1.25rem; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Checkout — Select Delivery Option</h2>
        <p>Available Bangladeshi couriers for your shipping address:</p>

        <div id="courier-list">
            <!-- Dynamically populated via AJAX or server render -->
            <div class="rate-option recommended">
                <div>
                    <strong>Pathao Courier</strong> <span class="badge">Cheapest</span>
                    <div style="font-size: 0.875rem; color: #64748b;">Est. 24-48 Hours Delivery</div>
                </div>
                <div class="price">৳ 60.00</div>
            </div>

            <div class="rate-option">
                <div>
                    <strong>Steadfast Courier</strong>
                    <div style="font-size: 0.875rem; color: #64748b;">Nationwide 24-72 Hours</div>
                </div>
                <div class="price">৳ 70.00</div>
            </div>

            <div class="rate-option">
                <div>
                    <strong>RedX Courier</strong>
                    <div style="font-size: 0.875rem; color: #64748b;">Doorstep Parcel Delivery</div>
                </div>
                <div class="price">৳ 85.00</div>
            </div>
        </div>
    </div>
</body>
</html>
