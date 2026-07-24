function initBuyLaterWidget() {
  if (window.buylaterInitialized) return;
  window.buylaterInitialized = true;
  window.buylaterUseSellingPlan = true;

  const triggerBtn = document.getElementById('buylater-trigger');
  const modal = document.getElementById('buylater-modal');
  const closeBtn = document.querySelector('.buylater-close-btn');
  const continueBtn = document.getElementById('buylater-continue-btn');
  
  // Steps and Forms
  const stepOptions = document.getElementById('buylater-step-options');
  const stepBook = document.getElementById('buylater-step-book');
  const stepRemind = document.getElementById('buylater-step-remind');
  const stepDiscount = document.getElementById('buylater-step-discount');
  
  const remindForm = document.getElementById('buylater-remind-form');
  const discountForm = document.getElementById('buylater-discount-form');
  const bookForm = document.getElementById('buylater-book-form');
  
  const messageDiv = document.getElementById('buylater-message');
  const optionCards = document.querySelectorAll('.buylater-option-card');
  const backBtns = document.querySelectorAll('.buylater-step-back-btn');

  if (!triggerBtn || !modal) return;

  let selectedOption = null;
  const rawPrice = (triggerBtn.getAttribute('data-product-price') || '0').replace(/,/g, '');
  const productPrice = parseFloat(rawPrice);
  const currencySymbol = window.buylaterCurrencySymbol || '$';
  let depositPercentage = window.buylaterDepositPercentage || 10; // Default fallback

  // Populate Booking Breakdown
  const breakdownPrice = document.getElementById('book-breakdown-price');
  const breakdownDeposit = document.getElementById('book-breakdown-deposit');
  const breakdownRemaining = document.getElementById('book-breakdown-remaining');

  function updateDepositDisplay() {
    const depositVal = (productPrice * (depositPercentage / 100)).toFixed(2);
    const depositText = document.getElementById('buylater-deposit-amount');
    if (depositText) {
      depositText.textContent = `From ${currencySymbol}${depositVal} deposit`;
    }

    const depositLabel = document.getElementById('book-deposit-label');
    if (depositLabel) {
      depositLabel.textContent = `Required Deposit (${depositPercentage}%):`;
    }
    if (breakdownPrice && breakdownDeposit && breakdownRemaining) {
      breakdownPrice.textContent = `${currencySymbol}${productPrice.toFixed(2)}`;
      breakdownDeposit.textContent = `${currencySymbol}${depositVal}`;
      breakdownRemaining.textContent = `${currencySymbol}${(productPrice - parseFloat(depositVal)).toFixed(2)}`;
    }
  }

  // Initial update
  updateDepositDisplay();

  // If hold duration days is already loaded from window, apply it
  if (window.buylaterHoldDurationDays) {
    const holdDaysSpan = document.getElementById('buylater-hold-days-display');
    if (holdDaysSpan) {
      holdDaysSpan.textContent = window.buylaterHoldDurationDays;
    }
  }

  // Fetch settings dynamically from the app proxy
  const shopDomain = window.buylaterShopDomain || new URL(window.location.href).hostname;
  const productId = triggerBtn.getAttribute('data-product-id') || '';
  fetch(`/apps/buylater-proxy/settings?shop=${encodeURIComponent(shopDomain)}&product_id=${encodeURIComponent(productId)}&t=${Date.now()}`, {
    headers: {
      'Accept': 'application/json'
    }
  })
  .then(res => {
    if (!res.ok) throw new Error('Failed to load settings');
    return res.json();
  })
  .then(data => {
    if (data) {
      if (data.enabled === false) {
        const btnWrapper = triggerBtn.closest('.buylater-btn-wrapper');
        if (btnWrapper) {
          btnWrapper.style.setProperty('display', 'none', 'important');
        } else {
          triggerBtn.style.display = 'none';
        }
        return;
      } else {
        const btnWrapper = triggerBtn.closest('.buylater-btn-wrapper');
        if (btnWrapper) {
          btnWrapper.style.setProperty('display', 'flex', 'important');
        } else {
          triggerBtn.style.display = 'inline-block';
        }
      }
      if (data.use_selling_plan || data.selling_plan_id) {
        window.buylaterUseSellingPlan = true;
        window.buylaterSellingPlanGroupId = data.selling_plan_group_id;
        if (data.selling_plan_id) {
          window.buylaterSellingPlanId = data.selling_plan_id;
        }
      }
      if (data.deposit_percentage) {
        depositPercentage = parseInt(data.deposit_percentage, 10);
        updateDepositDisplay();
      }
      if (data.hold_duration_days) {
        const holdDaysSpan = document.getElementById('buylater-hold-days-display');
        if (holdDaysSpan) {
          holdDaysSpan.textContent = data.hold_duration_days;
        }
      }
      if (data.button_text) {
        const btnTextSpan = triggerBtn.querySelector('span');
        if (btnTextSpan) {
          btnTextSpan.textContent = data.button_text;
        }
      }
      
      // Control options visibility based on shop settings
      const depositCard = document.querySelector('.buylater-option-card[data-option="book"]');
      const reminderCard = document.querySelector('.buylater-option-card[data-option="remind"]');
      const alertsCard = document.querySelector('.buylater-option-card[data-option="discount"]');

      if (depositCard && data.show_deposit === false) {
        depositCard.style.display = 'none';
      }
      if (reminderCard && data.show_reminders === false) {
        reminderCard.style.display = 'none';
      }
      if (alertsCard && data.show_alerts === false) {
        alertsCard.style.display = 'none';
      }

      // If all options are disabled, hide the trigger button completely
      if (data.show_deposit === false && data.show_reminders === false && data.show_alerts === false) {
        triggerBtn.style.display = 'none';
      }
    }
  })
  .catch(err => {
    console.warn('Could not fetch deposit settings, using default 10%', err);
  });

  // Set min datetime for reminder picker to current time
  const datetimeInput = document.getElementById('remind-datetime');
  if (datetimeInput) {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    datetimeInput.min = `${year}-${month}-${day}T${hours}:${minutes}`;
  }

  // Pre-fill email inputs if customer is logged in
  const customerEmail = window.buylaterCustomerEmail || '';
  if (customerEmail) {
    const bookEmailInput = document.getElementById('book-email');
    const remindEmailInput = document.getElementById('remind-email');
    const discountEmailInput = document.getElementById('discount-email');
    if (bookEmailInput) bookEmailInput.value = customerEmail;
    if (remindEmailInput) remindEmailInput.value = customerEmail;
    if (discountEmailInput) discountEmailInput.value = customerEmail;
  }

  // Open modal
  triggerBtn.addEventListener('click', function() {
    modal.style.display = 'flex';
    resetModal();
  });

  // Close modal
  closeBtn.addEventListener('click', function() {
    modal.style.display = 'none';
  });

  // Close on outside click
  window.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });

  // Reset modal state
  function resetModal() {
    messageDiv.style.display = 'none';
    messageDiv.className = 'buylater-message';
    
    // Reset steps
    stepOptions.classList.add('active');
    stepBook.classList.remove('active');
    stepRemind.classList.remove('active');
    stepDiscount.classList.remove('active');
    
    // Reset cards selection
    optionCards.forEach(card => card.classList.remove('selected'));
    selectedOption = null;
    continueBtn.disabled = true;
    continueBtn.classList.remove('enabled');
  }

  // Option Card Selection
  optionCards.forEach(card => {
    card.addEventListener('click', function() {
      optionCards.forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      
      selectedOption = card.getAttribute('data-option');
      continueBtn.disabled = false;
      continueBtn.classList.add('enabled');
    });
  });

  // Back Button Navigation
  backBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      messageDiv.style.display = 'none';
      stepBook.classList.remove('active');
      stepRemind.classList.remove('active');
      stepDiscount.classList.remove('active');
      stepOptions.classList.add('active');
    });
  });

  // Continue Button Action
  continueBtn.addEventListener('click', function() {
    if (!selectedOption) return;
    
    stepOptions.classList.remove('active');
    messageDiv.style.display = 'none';
    
    if (selectedOption === 'book') {
      stepBook.classList.add('active');
    } else if (selectedOption === 'remind') {
      stepRemind.classList.add('active');
    } else if (selectedOption === 'discount') {
      stepDiscount.classList.add('active');
    }
  });

  // Get current product data payload
  function getProductData() {
    // Try to find the selected variant ID dynamically
    const urlParams = new URLSearchParams(window.location.search);
    let variantId = urlParams.get('variant');

    if (!variantId) {
      const variantInput = document.querySelector('form[action*="/cart/add"] input[name="id"], form[action*="/cart/add"] select[name="id"]');
      if (variantInput) {
        variantId = variantInput.value;
      }
    }

    if (!variantId) {
      variantId = triggerBtn.getAttribute('data-variant-id');
    }

    return {
      product_id: triggerBtn.getAttribute('data-product-id'),
      variant_id: variantId,
      product_title: triggerBtn.getAttribute('data-product-title'),
      product_handle: triggerBtn.getAttribute('data-product-handle'),
      product_price: triggerBtn.getAttribute('data-product-price'),
      product_image: triggerBtn.getAttribute('data-product-image'),
      shop: window.buylaterShopDomain,
      currency: window.buylaterCurrencyCode || 'USD'
    };
  }

  // Helper to show messages
  function showMessage(text, type) {
    messageDiv.textContent = text;
    messageDiv.style.display = 'block';
    messageDiv.className = `buylater-message ${type}`;
    // Scroll to the message
    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // Handle network responses and check for content-type / password wall redirects
  function handleResponse(response) {
    const contentType = response.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
      return response.json().then(data => {
        if (!response.ok) {
          throw new Error(data.message || 'Something went wrong.');
        }
        return data;
      });
    } else {
      return response.text().then(text => {
        if (text.includes('/password') || text.includes('storefront_digest') || text.includes('password-page')) {
          throw new Error('Your storefront is password-protected. Please enter your storefront password or disable password protection in Shopify preferences.');
        }
        if (!response.ok) {
          throw new Error(`Server returned status ${response.status}: ${text.slice(0, 100)}`);
        }
        throw new Error('Expected JSON response, but received non-JSON content.');
      });
    }
  }

  // Submit Remind Me Later Form
  let isRemindSubmitting = false;
  remindForm.addEventListener('submit', function(e) {
    e.preventDefault();
    if (isRemindSubmitting) return;
    const email = document.getElementById('remind-email').value;
    const datetime = document.getElementById('remind-datetime').value;
    
    if (!email || !datetime) return;

    const submitBtn = remindForm.querySelector('.buylater-primary-btn');
    const originalBtnText = submitBtn.querySelector('span').textContent;
    
    isRemindSubmitting = true;
    submitBtn.disabled = true;
    submitBtn.classList.remove('enabled');
    submitBtn.querySelector('span').textContent = 'Setting Reminder...';
    messageDiv.style.display = 'none';

    let scheduledAtUtc = datetime;
    const dateObj = new Date(datetime);
    if (!isNaN(dateObj.getTime())) {
      scheduledAtUtc = dateObj.toISOString();
    }

    const payload = {
      ...getProductData(),
      email: email,
      scheduled_at: datetime,
      scheduled_at_utc: scheduledAtUtc
    };

    fetch('/apps/buylater-proxy/reminders', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(handleResponse)
    .then(data => {
      showMessage('Success! We will email you a reminder at the scheduled time.', 'success');
      remindForm.reset();
    })
    .catch(error => {
      console.error('Error:', error);
      showMessage(error.message || 'Failed to set reminder. Please try again.', 'error');
    })
    .finally(() => {
      isRemindSubmitting = false;
      submitBtn.disabled = false;
      submitBtn.classList.add('enabled');
      submitBtn.querySelector('span').textContent = originalBtnText;
    });
  });

  // Submit Price Drop Alert Form
  let isDiscountSubmitting = false;
  discountForm.addEventListener('submit', function(e) {
    e.preventDefault();
    if (isDiscountSubmitting) return;
    const email = document.getElementById('discount-email').value;
    
    if (!email) return;

    const submitBtn = discountForm.querySelector('.buylater-primary-btn');
    const originalBtnText = submitBtn.querySelector('span').textContent;
    
    isDiscountSubmitting = true;
    submitBtn.disabled = true;
    submitBtn.classList.remove('enabled');
    submitBtn.querySelector('span').textContent = 'Subscribing...';
    messageDiv.style.display = 'none';

    const payload = {
      ...getProductData(),
      email: email
    };

    fetch('/apps/buylater-proxy/discounts/subscribe', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(handleResponse)
    .then(data => {
      showMessage('Success! You will be notified when this item goes on sale.', 'success');
      discountForm.reset();
    })
    .catch(error => {
      console.error('Error:', error);
      showMessage(error.message || 'Failed to subscribe. Please try again.', 'error');
    })
    .finally(() => {
      isDiscountSubmitting = false;
      submitBtn.disabled = false;
      submitBtn.classList.add('enabled');
      submitBtn.querySelector('span').textContent = originalBtnText;
    });
  });

  // Submit Book It Now Form (Draft/Deposit Booking)
  let isBookSubmitting = false;
  bookForm.addEventListener('submit', function(e) {
    e.preventDefault();
    if (isBookSubmitting) return;
    const email = document.getElementById('book-email').value;
    
    if (!email) return;

    const submitBtn = bookForm.querySelector('.buylater-primary-btn');
    const originalBtnText = submitBtn.querySelector('span').textContent;
    
    isBookSubmitting = true;
    submitBtn.disabled = true;
    submitBtn.classList.remove('enabled');
    submitBtn.querySelector('span').textContent = 'Creating Reservation...';
    messageDiv.style.display = 'none';

    const payload = {
      ...getProductData(),
      email: email,
      deposit_percentage: depositPercentage
    };

    if (window.buylaterUseSellingPlan) {
      showMessage('Success! Adding deposit option to cart & redirecting to checkout...', 'success');
      const item = {
        id: payload.variant_id || payload.product_id,
        quantity: 1
      };
      if (window.buylaterSellingPlanId) {
        let planId = String(window.buylaterSellingPlanId);
        if (planId.includes('/')) {
          planId = planId.split('/').pop();
        }
        item.selling_plan = parseInt(planId, 10) || planId;
      }
      const cartBody = {
        items: [item]
      };
      fetch('/cart/clear.js', { method: 'POST' })
      .then(() => fetch('/cart/add.js', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(cartBody)
      }))
      .then(res => res.json())
      .then(cartData => {
        // Record pending booking in app database asynchronously
        fetch('/apps/buylater-proxy/bookings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ ...payload, payment_type: 'selling_plan' })
        }).catch(e => console.warn('Background booking record log error:', e));

        setTimeout(() => {
          window.top.location.href = '/checkout';
        }, 1000);
      })
      .catch(err => {
        console.warn('Native cart/add.js failed, falling back to standard proxy booking:', err);
        executeProxyBooking(payload);
      });
      return;
    }

    executeProxyBooking(payload);

    function executeProxyBooking(bookingPayload) {
      fetch('/apps/buylater-proxy/bookings', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(bookingPayload)
      })
      .then(handleResponse)
      .then(data => {
        showMessage('Success! Redirecting you to checkout to pay the deposit...', 'success');
        if (data.checkout_url) {
          setTimeout(() => {
            window.top.location.href = data.checkout_url;
          }, 1500);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showMessage(error.message || 'Failed to initialize booking. Please try again.', 'error');
      })
      .finally(() => {
        isBookSubmitting = false;
        submitBtn.disabled = false;
        submitBtn.classList.add('enabled');
        submitBtn.querySelector('span').textContent = originalBtnText;
      });
    }
  });
}

if (document.readyState === 'interactive' || document.readyState === 'complete') {
  initBuyLaterWidget();
} else {
  document.addEventListener('DOMContentLoaded', initBuyLaterWidget);
}

// Fallback Document Event Delegation so clicking trigger button ALWAYS opens modal
document.addEventListener('click', function(e) {
  const btn = e.target.closest('#buylater-trigger, .buylater-btn');
  if (btn) {
    const modal = document.getElementById('buylater-modal');
    const stepOptions = document.getElementById('buylater-step-options');
    if (modal) {
      modal.style.display = 'flex';
      if (stepOptions) {
        stepOptions.classList.add('active');
      }
    }
  }
});
