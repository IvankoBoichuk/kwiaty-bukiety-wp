{{--
  Fulfilment e-mail sent to the partner when an order reaches `processing`.

  Inline styles only: this is an e-mail, the theme stylesheet never reaches it.
--}}
@use('App\Shop\PartnerOrder')
@php
  /** @var PartnerOrder $order */
  $wc = $order->order;
  $currency = ['currency' => $wc->get_currency()];
@endphp

<div style="font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #222; line-height: 1.5">
  <h2 style="margin-bottom: 20px">{{ __('Nowe zamówienie do realizacji', 'sage-front') }}</h2>

  <p><strong>{{ __('Numer zamówienia:', 'sage-front') }}</strong> {{ $wc->get_order_number() }}</p>
  <p><strong>{{ __('Odbiorca:', 'sage-front') }}</strong> {{ $order->recipientName() }}</p>
  <p><strong>{{ __('Telefon odbiorcy:', 'sage-front') }}</strong> {{ $order->recipientPhone() }}</p>
  <p><strong>{{ __('Adres dostawy:', 'sage-front') }}</strong> {{ $order->fullAddress() }}</p>

  @if ($order->deliveryDate() !== '' || $order->deliveryTime() !== '')
    <p>
      <strong>{{ __('Data i godzina dostawy:', 'sage-front') }}</strong>
      {{
        trim(
          $order->deliveryDate() . ' ' . $order->deliveryTime(),
        )
      }}
    </p>
  @endif

  @if ($wc->get_customer_note() !== '')
    <p><strong>{{ __('Uwagi do zamówienia:', 'sage-front') }}</strong> {{ $wc->get_customer_note() }}</p>
  @endif

  <h3 style="margin-top: 25px">{{ __('Produkty:', 'sage-front') }}</h3>

  @foreach ($order->items() as $item)
    <div style="margin: 0 0 25px; padding: 0 0 20px; border-bottom: 1px solid #cccccc">
      <p style="margin: 0 0 6px">
        <strong>{{ $item['name'] }}</strong>
        @if ($item['flowers'] !== '')
          — {{ $item['flowers'] }}
        @endif
        × {{ $item['quantity'] }}
      </p>

      <p style="margin: 0 0 6px">
        <strong>{{ __('Kwota:', 'sage-front') }}</strong>
        {!! wc_price($item['partner_price'], $currency) !!}
      </p>

      @if ($item['card'] !== '')
        <p style="margin: 0 0 6px"><strong>{{ __('Treść bileciku:', 'sage-front') }}</strong> {{ $item['card'] }}</p>
      @endif

      @if ($item['notes'] !== '')
        <p style="margin: 0"><strong>{{ __('Uwagi:', 'sage-front') }}</strong> {{ $item['notes'] }}</p>
      @endif
    </div>
  @endforeach

  <h3 style="margin-top: 25px">
    {{ __('Łączna kwota:', 'sage-front') }} {!! wc_price($order->partnerTotal(), $currency) !!}
  </h3>
</div>
