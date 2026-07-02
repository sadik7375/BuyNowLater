document.addEventListener('DOMContentLoaded', function() {
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
  const productPrice = parseFloat(triggerBtn.getAttribute('data-product-price') || '0');
  const currencySymbol = '$'; // Adjust based on shop currency if needed
  let depositPercentage = 10; // Default fallback

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

  // Fetch settings dynamically from the app proxy
  const shopDomain = window.buylaterShopDomain || new URL(window.location.href).hostname;
  fetch(`/apps/buylater-proxy/settings?shop=${encodeURIComponent(shopDomain)}`, {
    headers: {
      'Accept': 'application/json'
    }
  })
  .then(res => {
    if (!res.ok) throw new Error('Failed to load settings');
    return res.json();
  })
  .then(data => {
    if (data && data.deposit_percentage) {
      depositPercentage = parseInt(data.deposit_percentage, 10);
      updateDepositDisplay();
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
    return {
      product_id: triggerBtn.getAttribute('data-product-id'),
      product_title: triggerBtn.getAttribute('data-product-title'),
      product_handle: triggerBtn.getAttribute('data-product-handle'),
      product_price: triggerBtn.getAttribute('data-product-price'),
      product_image: triggerBtn.getAttribute('data-product-image'),
      shop: window.buylaterShopDomain
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
  remindForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('remind-email').value;
    const datetime = document.getElementById('remind-datetime').value;
    
    if (!email || !datetime) return;

    const submitBtn = remindForm.querySelector('.buylater-primary-btn');
    const originalBtnText = submitBtn.querySelector('span').textContent;
    
    submitBtn.disabled = true;
    submitBtn.classList.remove('enabled');
    submitBtn.querySelector('span').textContent = 'Setting Reminder...';
    messageDiv.style.display = 'none';

    const payload = {
      ...getProductData(),
      email: email,
      scheduled_at: datetime
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
      submitBtn.disabled = false;
      submitBtn.classList.add('enabled');
      submitBtn.querySelector('span').textContent = originalBtnText;
    });
  });

  // Submit Price Drop Alert Form
  discountForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('discount-email').value;
    
    if (!email) return;

    const submitBtn = discountForm.querySelector('.buylater-primary-btn');
    const originalBtnText = submitBtn.querySelector('span').textContent;
    
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
      submitBtn.disabled = false;
      submitBtn.classList.add('enabled');
      submitBtn.querySelector('span').textContent = originalBtnText;
    });
  });

  // Submit Book It Now Form (Draft/Deposit Booking)
  bookForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('book-email').value;
    
    if (!email) return;

    const submitBtn = bookForm.querySelector('.buylater-primary-btn');
    const originalBtnText = submitBtn.querySelector('span').textContent;
    
    submitBtn.disabled = true;
    submitBtn.classList.remove('enabled');
    submitBtn.querySelector('span').textContent = 'Creating Reservation...';
    messageDiv.style.display = 'none';

    const payload = {
      ...getProductData(),
      email: email,
      deposit_percentage: depositPercentage
    };

    fetch('/apps/buylater-proxy/bookings', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(handleResponse)
    .then(data => {
      showMessage('Success! Redirecting you to checkout to pay the deposit...', 'success');
      if (data.checkout_url) {
        setTimeout(() => {
          window.location.href = data.checkout_url;
        }, 1500);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showMessage(error.message || 'Failed to initialize booking. Please try again.', 'error');
    })
    .finally(() => {
      submitBtn.disabled = false;
      submitBtn.classList.add('enabled');
      submitBtn.querySelector('span').textContent = originalBtnText;
    });
  });
});
