document.addEventListener('DOMContentLoaded', () => {
    const fieldContainer = document.querySelector('.field');
    if (!fieldContainer) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Générer la description';
    button.classList.add('btn', 'btn-link');
    fieldContainer.appendChild(button);

    const textarea = document.querySelector('#Product_description');
    const nameInput = document.querySelector('#Product_name');
    const subtitleInput = document.querySelector('#Product_subtitle');
    const priceInput = document.querySelector('#Product_price');
    const categoryInput = document.querySelector('#Product_category');
    const isBestInput = document.querySelector('#Product_isBest');

    button.addEventListener('click', () => {
        if (!textarea) return;
        button.disabled = true;
        button.textContent = 'Génération...';

        // Préparer les données du formulaire
        const productData = {
            name: nameInput?.value || '',
            subtitle: subtitleInput?.value || '',
            price: priceInput?.value || '',
            category: categoryInput?.value || '',
            isBest: isBestInput?.checked || false
        };

        fetch('/dust', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(productData)
        })
        .then(res => res.json())
        .then(data => {
            textarea.value = data.response || '';
            button.disabled = false;
            button.textContent = 'Générer la description avec l\'ia';
        })
        .catch(err => {
            console.error(err);
            button.disabled = false;
            button.textContent = 'Générer la description';
        });
    });
});
