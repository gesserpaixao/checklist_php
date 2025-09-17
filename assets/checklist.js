 const input = document.getElementById('meuCampo');
    const label = document.querySelector(`label[for="${input.id}"]`);

    if (input.required && input.value === '') {
        label.innerHTML += ' <span class="asterisco">*</span>';
    }