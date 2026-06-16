const plantAdd = document.querySelector('.cartButton');
const cartCount = document.querySelector('.icon-cart span');
const plantRem = document.querySelector('.cartButton2');

const savedCount = localStorage.getItem('cartCount');
if (savedCount !== null) {
    cartCount.innerText = parseInt(savedCount) >= 10 ? '9+' : savedCount;
}

function updateDisplay(num) {
    cartCount.innerText = num >= 10 ? '9+' : num;
    localStorage.setItem('cartCount', num);
}

plantAdd.addEventListener('click', function () {
    let num = parseInt(localStorage.getItem('cartCount')) || 0;
    num += 1;
    updateDisplay(num);
});

plantRem.addEventListener('click', function () {
    let num = parseInt(localStorage.getItem('cartCount')) || 0;
    if (num <= 0) return;
    num -= 1;
    updateDisplay(num);
});