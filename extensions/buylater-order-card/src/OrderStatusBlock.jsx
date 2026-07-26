import '@shopify/ui-extensions/preact';
import { render } from "preact";
import { useState, useEffect } from "preact/hooks";

export default async () => {
  render(<Extension />, document.body);
}

function Extension() {
  const [order, setOrder] = useState(shopify.order.value);
  const [lines, setLines] = useState(shopify.lines.value);
  const [booking, setBooking] = useState(null);
  const [loading, setLoading] = useState(true);

  // Subscribe to order changes
  useEffect(() => {
    return shopify.order.subscribe((val) => {
      setOrder(val);
    });
  }, []);

  // Subscribe to lines changes
  useEffect(() => {
    return shopify.lines.subscribe((val) => {
      setLines(val);
    });
  }, []);

  // Fetch booking details when order or lines changes
  useEffect(() => {
    if (!order) {
      setLoading(false);
      return;
    }

    const shopDomain = shopify.shop.myshopifyDomain;
    const orderId = order.id;
    const orderName = order.name;

    // Search for the booking token in line item custom attributes
    let token = "";
    if (lines && lines.length > 0) {
      for (const line of lines) {
        if (line.attributes && line.attributes.length > 0) {
          for (const attr of line.attributes) {
            if (attr.key === "_token" || attr.key === "_buylater_token" || attr.key === "buylater_token") {
              token = attr.value;
              break;
            }
          }
        }
        if (token) break;
      }
    }

    // Use storefront URL to route requests via the app proxy dynamically
    const fetchUrl = `https://${shopDomain}/apps/buylater-proxy/order-booking?shop=${encodeURIComponent(shopDomain)}&order_id=${encodeURIComponent(orderId)}&order_name=${encodeURIComponent(orderName || '')}&token=${encodeURIComponent(token)}`;

    setLoading(true);
    fetch(fetchUrl)
      .then((res) => res.json())
      .then((data) => {
        if (data && data.booking) {
          setBooking(data.booking);
        } else {
          setBooking(null);
        }
        setLoading(false);
      })
      .catch((err) => {
        console.error("Error fetching booking:", err);
        setBooking(null);
        setLoading(false);
      });
  }, [order, lines]);

  if (loading) {
    return (
      <s-stack border="base" borderRadius="base" padding="base" direction="inline" gap="base" alignItems="center">
        <s-spinner size="small" />
        <s-text color="subdued">Checking Buy Now Later reservation...</s-text>
      </s-stack>
    );
  }

  if (!booking) {
    return null;
  }

  // Format currency helper
  const formatMoney = (amount, currencyCode) => {
    const locale = shopify.localization.language.value || 'en';
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currencyCode || 'USD'
    }).format(amount);
  };

  // Format date helper
  const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const locale = shopify.localization.language.value || 'en';
    return new Intl.DateTimeFormat(locale, {
      dateStyle: 'medium'
    }).format(new Date(dateStr));
  };

  // Status mapping to tone
  let badgeTone = 'auto';
  let statusText = 'Deposit Paid';

  if (booking.payment_status === 'refunded') {
    badgeTone = 'critical';
    statusText = 'Refunded';
  } else if (booking.status === 'completed') {
    badgeTone = 'neutral';
    statusText = 'Completed';
  } else if (booking.status === 'expired') {
    badgeTone = 'critical';
    statusText = 'Expired';
  } else if (booking.status === 'pending') {
    badgeTone = 'neutral';
    statusText = 'Pending Deposit';
  } else if (booking.status === 'deposit_paid') {
    badgeTone = 'neutral';
    statusText = 'Deposit Paid';
  }

  // Expiry calculation
  const expiresAt = booking.expires_at;

  return (
    <s-stack border="base" borderRadius="base" padding="base" direction="block" gap="base">
      
      {/* Header Row */}
      <s-stack direction="inline" gap="base" alignItems="center" justifyContent="space-between">
        <s-text type="strong" size="large">Buy Now Later Booking</s-text>
        <s-badge tone={badgeTone}>{statusText}</s-badge>
      </s-stack>

      <s-divider />

      {/* Details Section */}
      <s-stack direction="block" gap="tight">
        <s-stack direction="inline" gap="base" justifyContent="space-between">
          <s-text color="subdued">Deposit Paid</s-text>
          <s-text type="strong">{formatMoney(booking.deposit_amount, booking.currency)}</s-text>
        </s-stack>
        
        <s-stack direction="inline" gap="base" justifyContent="space-between">
          <s-text color="subdued">Remaining Balance</s-text>
          <s-text type="strong">{formatMoney(booking.remaining_balance, booking.currency)}</s-text>
        </s-stack>

        {booking.status === 'deposit_paid' && booking.payment_status !== 'refunded' && expiresAt && (
          <s-stack direction="inline" gap="base" justifyContent="space-between">
            <s-text color="subdued">Hold Expiry Date</s-text>
            <s-text tone="warning" type="strong">{formatDate(expiresAt)}</s-text>
          </s-stack>
        )}
      </s-stack>

      {/* Action Button */}
      {booking.status === 'deposit_paid' && booking.payment_status !== 'refunded' && booking.checkout_url && (
        <s-stack direction="block" paddingBlockStart="base">
          <s-button href={booking.checkout_url} target="_blank" variant="primary" inlineSize="fill">
            Pay Remaining Balance
          </s-button>
        </s-stack>
      )}
    </s-stack>
  );
}