function fn_occupants (name, type) {
  const inputField = document.getElementById('edit-'+name);

  const currentValue = Number(inputField.value) || 0;
  if (type == '-') {
    inputField.value = currentValue - 1
  } else {
    inputField.value = currentValue + 1;
  }

  if (inputField.value < 0) {
    inputField.value = 0;
  }

  if (name === 'kid') {
    syncChildAgePanel();
  }
}

// Renders one "age" dropdown per child in #child-ages-panel, matching the
// current Kids Count — RateGain prices children by age, and previously
// every child was silently sent as a hardcoded age 5 with no way for the
// user to say otherwise.
function syncChildAgePanel () {
  const kidField = document.getElementById('edit-kid');
  const panel = document.getElementById('child-ages-panel');
  if (!kidField || !panel) {
    return;
  }

  const count = Math.max(0, Number(kidField.value) || 0);
  const existingValues = Array.from(panel.querySelectorAll('select')).map((select) => select.value);
  const prefill = (typeof window.srChildAges !== 'undefined' && window.srChildAges.length) ? window.srChildAges : existingValues;

  panel.innerHTML = '';

  if (count === 0) {
    panel.classList.remove('open');
    return;
  }
  panel.classList.add('open');

  for (let i = 0; i < count; i++) {
    const row = document.createElement('div');
    row.className = 'child-age-row';

    const label = document.createElement('label');
    label.textContent = 'Child ' + (i + 1) + ' age';

    const select = document.createElement('select');
    select.name = 'child_age[]';
    select.className = 'form-select form-element form-element--type-select';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select age';
    select.appendChild(placeholder);

    for (let age = 0; age <= 17; age++) {
      const option = document.createElement('option');
      option.value = String(age);
      option.textContent = age === 0 ? 'Under 1 year' : age + (age === 1 ? ' year' : ' years');
      select.appendChild(option);
    }

    const savedValue = prefill[i];
    if (savedValue !== undefined && savedValue !== '' && savedValue !== null) {
      select.value = String(savedValue);
    }

    row.appendChild(label);
    row.appendChild(select);
    panel.appendChild(row);
  }
}

document.addEventListener('DOMContentLoaded', syncChildAgePanel);

// Close the child-ages panel on an outside click — it otherwise stays open
// indefinitely once kids > 0, covering the rest of the search form/results.
document.addEventListener('click', function (event) {
  const panel = document.getElementById('child-ages-panel');
  if (!panel || !panel.classList.contains('open')) {
    return;
  }
  if (!event.target.closest('.add-kids')) {
    panel.classList.remove('open');
  }
});
