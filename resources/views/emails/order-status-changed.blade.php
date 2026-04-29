<h1>Tu pedido fue actualizado</h1>

<p>Hola {{ $order->user->name }}, tu pedido {{ $order->number }} cambio de {{ $previousStatus }} a {{ $order->status }}.</p>
<p>Puedes consultar el historial desde tu cuenta.</p>
