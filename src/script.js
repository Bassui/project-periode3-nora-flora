document.addEventListener('DOMContentLoaded', function () {
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');

    addToCartButtons.forEach(button => {
        button.addEventListener('click', function () {
            const item = this.parentElement;
            const name = item.querySelector('span:first-child').textContent;
            const price = item.querySelector('span:nth-child(2)').textContent;

            addToCart(name, price);
            alert(`${name} toegevoegd aan winkelwagen!`);
        });
    });

    // Display cart if on cart page
    if (document.getElementById('cart-items')) {
        displayCart();
    }

    // Clear cart button
    const clearCartBtn = document.getElementById('clear-cart');
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', function () {
            localStorage.removeItem('cart');
            displayCart();
        });
    }

    function addToCart(name, price) {
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        cart.push({ name, price });
        localStorage.setItem('cart', JSON.stringify(cart));
    }

    function displayCart() {
        const cartItems = document.getElementById('cart-items');
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        cartItems.innerHTML = '';
        if (cart.length === 0) {
            cartItems.innerHTML = '<p>Uw winkelwagen is leeg.</p>';
        } else {
            cart.forEach((item, index) => {
                const itemDiv = document.createElement('div');
                itemDiv.innerHTML = `<p>${item.name} - ${item.price} <button onclick="removeFromCart(${index})">Verwijder</button></p>`;
                cartItems.appendChild(itemDiv);
            });
        }
    }

    window.removeFromCart = function (index) {
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        cart.splice(index, 1);
        localStorage.setItem('cart', JSON.stringify(cart));
        displayCart();
    };
});