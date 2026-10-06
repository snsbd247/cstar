// Small progressive enhancements for the public website (works without JS too).

// Mobile menu
const toggle = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');
if (toggle && menu) {
    toggle.addEventListener('click', () => {
        const open = menu.classList.toggle('hidden') === false;
        toggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('overflow-hidden', open);
    });
}

// Appointment form: only list therapists who provide the chosen service.
const service = document.querySelector('#service_id');
const therapist = document.querySelector('#preferred_therapist_id');
if (service && therapist) {
    const filter = () => {
        const chosen = service.value;
        [...therapist.options].forEach((option) => {
            if (!option.value) return;
            const offers = (option.dataset.services || '').split(',');
            option.hidden = chosen !== '' && !offers.includes(chosen);
        });
        if (therapist.selectedOptions[0]?.hidden) therapist.value = '';
    };
    service.addEventListener('change', filter);
    filter();
}
