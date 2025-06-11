<!DOCTYPE html>
<html>
<head>
    <title>Stripe Payment</title>
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
@if(session('success'))
    <h3>{{ session('success') }}</h3>
@endif

<form action="{{ route('payment.process') }}" method="POST" id="payment-form">
    @csrf
    <input type="text" name="name" placeholder="Your Name" required>
    <div id="card-element"></div>
    <button type="submit">Pay $10</button>
</form>

<script>
    const stripe = Stripe('{{ config('services.stripe.key') }}');
    const elements = stripe.elements();
    const card = elements.create('card');
    card.mount('#card-element');

    const form = document.getElementById('payment-form');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const {token, error} = await stripe.createToken(card);
        if (error) {
            alert(error.message);
        } else {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'stripeToken';
            hiddenInput.value = token.id;
            form.appendChild(hiddenInput);
            form.submit();
        }
    });
</script>
</body>
</html>
