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
}
