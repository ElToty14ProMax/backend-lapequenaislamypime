<h1>Pago confirmado</h1>

<p>Hola {{ $order->user->name }}, recibimos el pago de tu pedido {{ $order->number }}.</p>
<p>Factura: {{ $order->invoice?->number }}</p>
<p>Total: {{ $order->currency }} {{ number_format($order->total_cents / 100, 2) }}</p>
<p>Gracias por comprar en La pequeña isla.</p>
