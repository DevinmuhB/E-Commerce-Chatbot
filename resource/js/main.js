// open & close Cart
var cart = document.querySelector('.cart');
function open_cart() { cart.classList.add("active"); }
function close_cart() { cart.classList.remove("active"); }

// open & close menu
var menu = document.querySelector('#menu');
function open_menu() { menu.classList.add("active"); }
function close_menu() { menu.classList.remove("active"); }

// open & close filter
var filter = document.querySelector('.filter');
function open_filter() { filter.classList.add("active"); }
function close_filter() { filter.classList.remove("active"); }

// change item image
let bigImage = document.getElementById("bigImg");
function ChangeItemImage(img) { bigImage.src = img; }

// Dropdown profile
const profileDropdown = document.getElementById('profileDropdown');
const dropdownContent = document.getElementById('dropdownContent');
let timeout;
if (profileDropdown) {
    profileDropdown.addEventListener('mouseenter', () => {
        clearTimeout(timeout);
        dropdownContent.style.display = 'block';
    });
    profileDropdown.addEventListener('mouseleave', () => {
        timeout = setTimeout(() => {
            dropdownContent.style.display = 'none';
        }, 300);
    });
}

// =========================
// TOMBOL KERANJANG
// =========================
document.addEventListener("DOMContentLoaded", function () {
    loadCart();

    document.querySelectorAll(".btnKeranjang").forEach(btn => {
        btn.addEventListener("click", function () {
            const id_produk = this.dataset.id;
    
            fetch("add-to-cart.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "id_produk=" + id_produk
            })
            .then(res => res.text())
            .then(response => {
                if (response.trim() === "success") {
                    loadCart(); // update keranjang tanpa refresh
                    document.querySelector(".cart").classList.add("active"); // buka popup cart
                } else {
                    alert("Gagal menambahkan ke keranjang: " + response);
                }
            });
        });
    });    
});

// =========================
// LOAD CART TANPA DISKON
// =========================
function loadCart() {
    fetch("get-cart.php")
        .then(res => res.json())
        .then(data => {
            const container = document.querySelector(".items_in_cart");
            const count = document.querySelector(".count_item_cart");
            const total = document.querySelector(".price_cart_total");

            container.innerHTML = "";
            let jumlahItem = 0;

            data.items.forEach(item => {
                jumlahItem += Number(item.jumlah);

                container.innerHTML += `
                    <div class="cart_item" style="display:flex; gap:10px; margin-bottom:10px;">
                        <img src="${item.path_foto}" width="50">
                        <div>
                            <p>${item.nama_produk}</p>
                            <p>
                                Rp ${item.harga.toLocaleString('id-ID')} × ${item.jumlah}
                            </p>
                            <div class="qty_control" style="margin-top:5px;">
                                <button class="btnKurang" data-id="${item.id_produk}">−</button>
                                <button class="btnTambah" data-id="${item.id_produk}">+</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            count.textContent = `(${data.items.length} Item in Cart)`;
            total.textContent = `Rp ${data.total.toLocaleString('id-ID')}`;
            document.querySelector(".count_item").textContent = Number(jumlahItem);

            // Re-bind button event setelah isi keranjang di-refresh
            document.querySelectorAll(".btnTambah").forEach(btn => {
                btn.addEventListener("click", function () {
                    updateCart(btn.dataset.id, 'tambah');
                });
            });

            document.querySelectorAll(".btnKurang").forEach(btn => {
                btn.addEventListener("click", function () {
                    updateCart(btn.dataset.id, 'kurang');
                });
            });
        });
}

// =========================
// UPDATE JUMLAH DI KERANJANG
// =========================
function updateCart(id_produk, aksi) {
    fetch("update-cart.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `id_produk=${id_produk}&aksi=${aksi}`
    })
    .then(res => res.text())
    .then(response => {
        if (response.trim() === "success") {
            loadCart(); // ini update isi cart langsung
        } else {
            alert("Gagal memperbarui keranjang: " + response);
        }
    });
}

function bindBtnKeranjang() {
    document.querySelectorAll(".btnKeranjang").forEach(btn => {
        btn.addEventListener("click", function () {
            const id_produk = this.dataset.id;

            fetch("add-to-cart.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "id_produk=" + id_produk
            })
            .then(res => res.text())
            .then(response => {
                if (response.trim() === "success") {
                    loadCart(); // update isi keranjang
                    document.querySelector(".cart").classList.add("active");
                } else {
                    alert("Gagal menambahkan ke keranjang: " + response);
                }
            });
        });
    });
}