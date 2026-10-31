// assets/js/app.js - Cafe Kamaal Hotel Bill Calculator

function updateCalculationPreview() {
    const priceInput = document.getElementById('total_price');
    const formulaEl = document.getElementById('previewDividerFormula');
    const perPersonEl = document.getElementById('previewPerPerson');
    const codesEl = document.getElementById('previewSelectedCodes');
    const submitBtn = document.getElementById('submitMealBtn');

    if (!priceInput) return;

    const price = parseFloat(priceInput.value) || 0;
    const checkedBoxes = Array.from(document.querySelectorAll('.hosteller-check:checked'));
    const count = checkedBoxes.length;

    const selectedCodes = checkedBoxes.map(cb => cb.dataset.code || '').filter(Boolean);

    if (count > 0 && price > 0) {
        const perPerson = (price / count).toFixed(2);
        formulaEl.textContent = `Rs. ${price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} ÷ ${count} eater${count > 1 ? 's' : ''}`;
        perPersonEl.textContent = `Rs. ${parseFloat(perPerson).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        codesEl.innerHTML = selectedCodes.map(c => `<span class="inline-block bg-slate-800 text-white font-mono text-[11px] px-1.5 py-0.5 rounded font-bold mr-1">${c}</span>`).join('');
        if (submitBtn) submitBtn.disabled = false;
    } else {
        formulaEl.textContent = `Rs. ${price.toFixed(2)} ÷ ${count} eaters`;
        perPersonEl.textContent = 'Rs. 0.00';
        codesEl.textContent = selectedCodes.length ? selectedCodes.join(', ') : 'None';
    }
}

function selectAllCodes(check) {
    document.querySelectorAll('.hosteller-check').forEach(cb => {
        cb.checked = check;
    });
    updateCalculationPreview();
}

function openAddMealModal() {
    const modal = document.getElementById('mealModal');
    const form = document.getElementById('mealForm');
    const title = document.getElementById('mealModalTitle');
    const action = document.getElementById('formAction');
    const mealId = document.getElementById('formMealId');
    const editNotice = document.getElementById('editNoticeBox');
    const changeReason = document.getElementById('changeReasonField');
    const submitBtnText = document.getElementById('submitBtnText');

    if (!modal) return;

    form.reset();
    action.value = 'create';
    mealId.value = '';
    title.textContent = 'Add Hotel Meal Entry';
    submitBtnText.textContent = 'Save Meal Entry';
    if (editNotice) editNotice.classList.add('hidden');
    if (changeReason) changeReason.classList.add('hidden');

    // Default select all hostellers for convenience
    selectAllCodes(true);

    // Set today's date & time
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];
    const timeStr = now.toTimeString().split(' ')[0].substring(0, 5);
    const dateInput = document.getElementById('meal_date');
    const timeInput = document.getElementById('meal_time');
    if (dateInput) dateInput.value = dateStr;
    if (timeInput) timeInput.value = timeStr;

    modal.classList.remove('hidden');
    document.getElementById('meal_title')?.focus();
    updateCalculationPreview();
}

function openEditMealModal(id) {
    const modal = document.getElementById('mealModal');
    const form = document.getElementById('mealForm');
    const title = document.getElementById('mealModalTitle');
    const action = document.getElementById('formAction');
    const mealId = document.getElementById('formMealId');
    const editNotice = document.getElementById('editNoticeBox');
    const changeReason = document.getElementById('changeReasonField');
    const submitBtnText = document.getElementById('submitBtnText');

    if (!modal) return;

    // Fetch meal details from API
    fetch(`meal_actions.php?action=get&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Error fetching meal details.');
                return;
            }

            const meal = data.meal;
            action.value = 'update';
            mealId.value = meal.id;
            title.textContent = `Edit Meal Entry #${meal.id}`;
            submitBtnText.textContent = 'Save & Recalculate Split';
            if (editNotice) editNotice.classList.remove('hidden');
            if (changeReason) changeReason.classList.remove('hidden');

            document.getElementById('meal_title').value = meal.title;
            document.getElementById('meal_date').value = meal.meal_date;
            document.getElementById('meal_time').value = meal.meal_time.substring(0, 5);
            document.getElementById('meal_type').value = meal.meal_type;
            document.getElementById('total_price').value = parseFloat(meal.total_price).toFixed(2);
            document.getElementById('edit_summary').value = '';

            // Mark checkboxes for eaters
            const eaterUserIds = meal.eaters.map(e => parseInt(e.user_id));
            document.querySelectorAll('.hosteller-check').forEach(cb => {
                cb.checked = eaterUserIds.includes(parseInt(cb.value));
            });

            modal.classList.remove('hidden');
            updateCalculationPreview();
        })
        .catch(err => {
            console.error(err);
            alert('Failed to connect to server to fetch meal details.');
        });
}

function closeMealModal() {
    const modal = document.getElementById('mealModal');
    if (modal) modal.classList.add('hidden');
}

// Close modal when clicking outside of it
window.addEventListener('click', function(e) {
    const modal = document.getElementById('mealModal');
    if (e.target === modal) {
        closeMealModal();
    }
});

// Copy credentials to clipboard helper
function copyToClipboard(text, alertMsg = 'Copied to clipboard!') {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            alert(alertMsg);
        }).catch(err => {
            fallbackCopy(text, alertMsg);
        });
    } else {
        fallbackCopy(text, alertMsg);
    }
}

function fallbackCopy(text, alertMsg) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
        alert(alertMsg);
    } catch (err) {
        prompt("Copy this text manually:", text);
    }
    document.body.removeChild(textArea);
}

// Ensure calculation preview runs on initial load if elements exist
document.addEventListener('DOMContentLoaded', function() {
    updateCalculationPreview();
    initPWA();
});

// -------------------------------------------------------------
// PWA & Mobile Install Manager
// -------------------------------------------------------------
let deferredPrompt = null;

function initPWA() {
    // 1. Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js')
            .then(reg => {
                console.log('Cafe Kamaal PWA Service Worker Registered', reg.scope);
            })
            .catch(err => {
                console.log('Service Worker registration skipped:', err);
            });
    }

    // 2. Check if already installed / running in standalone mode
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (isStandalone) {
        // App is already installed and opened as native app
        const banner = document.getElementById('pwaInstallBanner');
        if (banner) banner.remove();
        return;
    }

    // 3. Listen for Android / Chrome install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        const banner = document.getElementById('pwaInstallBanner');
        if (banner && sessionStorage.getItem('pwa_banner_dismissed') !== 'true') {
            banner.classList.remove('hidden');
        }
    });

    // 4. Detect iOS Safari (iPhone / iPad)
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    if (isIos && !isStandalone && sessionStorage.getItem('pwa_banner_dismissed') !== 'true') {
        const banner = document.getElementById('pwaInstallBanner');
        if (banner) {
            banner.classList.remove('hidden');
        }
    }
}

function triggerPWAInstall() {
    if (deferredPrompt) {
        // Android / Desktop Chrome PWA prompt
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User installed Cafe Kamaal PWA');
            }
            deferredPrompt = null;
            dismissPWAInstall();
        });
    } else {
        // iOS or fallback instructions
        const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent);
        if (isIos) {
            alert('To install Cafe Kamaal on iPhone:\n1. Tap the Share button at the bottom of Safari.\n2. Scroll down and tap "Add to Home Screen".');
        } else {
            alert('To install Cafe Kamaal on your phone:\nTap the three dots menu (⋮) in your browser and select "Install app" or "Add to Home screen".');
        }
    }
}

function dismissPWAInstall() {
    const banner = document.getElementById('pwaInstallBanner');
    if (banner) banner.classList.add('hidden');
    sessionStorage.setItem('pwa_banner_dismissed', 'true');
}
