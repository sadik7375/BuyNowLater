function initBuyLaterWidget() {
  if (window.buylaterInitialized) return;
  window.buylaterInitialized = true;
  window.buylaterUseSellingPlan = window.buylaterUseSellingPlan || false;

  // Hiding default theme selling plan selectors
  function hideDefaultSellingPlanSelectors() {
    const selectors = [
      'fieldset.product-form__input--selling-plan-group',
      '.product-form__input--selling-plan-group',
      '.selling-plan-allocation',
      '.selling-plan-selector',
      '[name="selling_plan"]',
      '.product-form__selling-plan',
      '.shopify-selling-plans',
      '.selling-plans',
      '.product-option-selling-plan',
      '.selling-plan-group-selector',
      '.selling-plan-selector-container'
    ];
    selectors.forEach(sel => {
      document.querySelectorAll(sel).forEach(el => {
        el.style.setProperty('display', 'none', 'important');
      });
    });

    document.querySelectorAll('select, input, fieldset, div, span').forEach(el => {
      const name = el.getAttribute('name') || '';
      const id = el.getAttribute('id') || '';
      const className = el.className || '';
      if (
        name.includes('selling_plan') || 
        id.includes('selling_plan') || 
        (typeof className === 'string' && className.includes('selling-plan'))
      ) {
        el.style.setProperty('display', 'none', 'important');
      }
    });
  }

  function setupHidingObserver() {
    hideDefaultSellingPlanSelectors();
    const observer = new MutationObserver(() => {
      hideDefaultSellingPlanSelectors();
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

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

  setupHidingObserver();

  let selectedOption = null;
  let dynamicProductPrice = parseFloat((triggerBtn.getAttribute('data-product-price') || '0').replace(/,/g, ''));
  const currencySymbol = window.buylaterCurrencySymbol || '$';
  let depositPercentage = window.buylaterDepositPercentage || 10; // Default fallback

  // Populate Booking Breakdown
  const breakdownPrice = document.getElementById('book-breakdown-price');
  const breakdownDeposit = document.getElementById('book-breakdown-deposit');
  const breakdownRemaining = document.getElementById('book-breakdown-remaining');

  function updateDepositDisplay() {
    const depositVal = (dynamicProductPrice * (depositPercentage / 100)).toFixed(2);
    const depositText = document.getElementById('buylater-deposit-amount');
    if (depositText) {
      depositText.textContent = `From ${currencySymbol}${depositVal} deposit`;
    }

    const depositLabel = document.getElementById('book-deposit-label');
    if (depositLabel) {
      depositLabel.textContent = `Required Deposit (${depositPercentage}%):`;
    }
    if (breakdownPrice && breakdownDeposit && breakdownRemaining) {
      breakdownPrice.textContent = `${currencySymbol}${dynamicProductPrice.toFixed(2)}`;
      breakdownDeposit.textContent = `${currencySymbol}${depositVal}`;
      breakdownRemaining.textContent = `${currencySymbol}${(dynamicProductPrice - parseFloat(depositVal)).toFixed(2)}`;
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
          triggerBtn.style.display = 'inline-flex';
        }
      }
      if (data.limit_reached) {
        window.buylaterLimitReached = true;
      } else {
        window.buylaterLimitReached = false;
      }
      if (data.deposit_percentage) {
        depositPercentage = parseInt(data.deposit_percentage, 10);
        updateDepositDisplay();
      }
      if (data.use_selling_plan && data.selling_plan_id) {
        window.buylaterUseSellingPlan = true;
        window.buylaterSellingPlanGroupId = data.selling_plan_group_id;
        window.buylaterSellingPlanId = data.selling_plan_id;
      } else {
        window.buylaterUseSellingPlan = false;
        window.buylaterSellingPlanGroupId = null;
        window.buylaterSellingPlanId = null;
      }
      if (data.hold_duration_days) {
        const holdDaysSpan = document.getElementById('buylater-hold-days-display');
        if (holdDaysSpan) {
          holdDaysSpan.textContent = data.hold_duration_days;
        }
      }
      if (data.terms_text) {
        const termsNote = document.getElementById('buylater-terms-note');
        if (termsNote) {
          termsNote.textContent = data.terms_text;
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

  // Robust Variant Detection & Syncing function
  window.syncSelectedVariant = function(callback) {
    const urlParams = new URLSearchParams(window.location.search);
    let variantId = urlParams.get('variant');

    if (!variantId) {
      const variantInput = document.querySelector('form[action*="/cart/add"] input[name="id"], form[action*="/cart/add"] select[name="id"], input[name="id"]');
      if (variantInput) {
        variantId = variantInput.value;
      }
    }

    if (!variantId) {
      variantId = triggerBtn.getAttribute('data-variant-id');
    }

    if (variantId && String(variantId).includes('/')) {
      variantId = String(variantId).split('/').pop();
    }

    const productHandle = triggerBtn.getAttribute('data-product-handle');
    if (!productHandle) {
      if (callback) callback();
      return;
    }

    fetch(`/products/${productHandle}.js`)
      .then(res => res.json())
      .then(product => {
        const variant = product.variants.find(v => String(v.id) === String(variantId)) || product.variants[0];
        if (variant) {
          triggerBtn.setAttribute('data-variant-id', variant.id);
          
          const newPrice = (variant.price / 100.0);
          dynamicProductPrice = newPrice;
          triggerBtn.setAttribute('data-product-price', newPrice.toFixed(2));
          
          if (variant.compare_at_price) {
            const comparePrice = (variant.compare_at_price / 100.0);
            triggerBtn.setAttribute('data-product-compare-price', comparePrice.toFixed(2));
          } else {
            triggerBtn.removeAttribute('data-product-compare-price');
          }

          if (variant.featured_image && variant.featured_image.src) {
            triggerBtn.setAttribute('data-product-image', variant.featured_image.src);
          }

          // Update modal UI elements
          const previewImg = document.getElementById('buylater-preview-img');
          const previewTitle = document.getElementById('buylater-preview-title');
          const previewPrice = document.getElementById('buylater-preview-price');
          const previewCompare = document.getElementById('buylater-preview-compare');

          if (previewImg && variant.featured_image && variant.featured_image.src) {
            previewImg.src = variant.featured_image.src;
          }
          if (previewTitle) {
            previewTitle.textContent = variant.title !== 'Default Title' ? `${product.title} - ${variant.title}` : product.title;
          }

          if (previewPrice) {
            previewPrice.textContent = `${currencySymbol}${newPrice.toFixed(2)}`;
          }
          if (previewCompare) {
            if (variant.compare_at_price) {
              const comparePrice = (variant.compare_at_price / 100.0);
              previewCompare.textContent = `${currencySymbol}${comparePrice.toFixed(2)}`;
              previewCompare.style.display = 'inline';
            } else {
              previewCompare.style.display = 'none';
            }
          }

          updateDepositDisplay();
        }
        if (callback) callback();
      })
      .catch(err => {
        console.error('Error fetching product JSON for variant sync:', err);
        if (callback) callback();
      });
  };

  // Open modal
  triggerBtn.addEventListener('click', function() {
    window.syncSelectedVariant(function() {
      modal.style.display = 'flex';
      resetModal();
    });
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
    stepOptions.classList.remove('active');
    stepBook.classList.add('active');
    stepRemind.classList.remove('active');
    stepDiscount.classList.remove('active');
    
    // Reset cards selection
    optionCards.forEach(card => card.classList.remove('selected'));
    selectedOption = 'book';
    continueBtn.disabled = true;
    continueBtn.classList.remove('enabled');

    if (window.buylaterLimitReached) {
      showMessage('Notice: This store has reached its deposit reservation limit. Reservations are temporarily unavailable.', 'error');
    }
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
    let variantId = triggerBtn.getAttribute('data-variant-id');
    if (variantId && String(variantId).includes('/')) {
      variantId = String(variantId).split('/').pop();
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
    let email = document.getElementById('book-email') ? document.getElementById('book-email').value : '';
    if (!email) {
      email = window.buylaterCustomerEmail || 'guest@example.com';
    }

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
      const token = Array.from({length: 32}, () => Math.floor(Math.random() * 16).toString(16)).join('');
      
      let cleanVariantId = payload.variant_id || payload.product_id;
      if (cleanVariantId && String(cleanVariantId).includes('/')) {
        cleanVariantId = String(cleanVariantId).split('/').pop();
      }

      const item = {
        id: parseInt(cleanVariantId, 10) || cleanVariantId,
        quantity: 1,
        properties: {
          _token: token,
          buylater_token: token
        }
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
      .then(res => {
        if (!res.ok) throw new Error('Cart clear failed');
        return fetch('/cart/add.js', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(cartBody)
        });
      })
      .then(res => {
        if (!res.ok) {
          return res.json().then(errData => {
            throw new Error(errData.description || errData.message || 'Cart add failed');
          });
        }
        return res.json();
      })
      .then(cartData => {
        fetch('/apps/buylater-proxy/bookings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ ...payload, token: token, payment_type: 'selling_plan' })
        }).catch(e => console.warn('Background booking record log error:', e));

        setTimeout(() => {
          window.top.location.href = '/checkout';
        }, 1500);
      })
      .catch(err => {
        console.warn('Native cart/add.js failed, falling back to standard proxy booking:', err);
        executeProxyBooking({ ...payload, payment_type: 'draft_order' });
      });
    } else {
      executeProxyBooking(payload);
    }

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
          let targetUrl = data.checkout_url;
          // Ensure draft order invoice URLs stay on current shop domain to keep storefront password session
          if (targetUrl.includes('/invoices/')) {
            try {
              const urlObj = new URL(targetUrl, window.location.origin);
              targetUrl = urlObj.pathname + urlObj.search;
            } catch (e) {}
          }
          setTimeout(() => {
            window.top.location.href = targetUrl;
          }, 1200);
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
    if (modal) {
      if (typeof window.syncSelectedVariant === 'function') {
        window.syncSelectedVariant(function() {
          modal.style.display = 'flex';
          const stepOptions = document.getElementById('buylater-step-options');
          if (stepOptions) {
            stepOptions.classList.add('active');
          }
        });
      } else {
        modal.style.display = 'flex';
        const stepOptions = document.getElementById('buylater-step-options');
        if (stepOptions) {
          stepOptions.classList.add('active');
        }
      }
    }
  }
});

