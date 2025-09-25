// ================== Hover Navigation ==================
let list = document.querySelectorAll(".navigation li");

function activeLink() {
  list.forEach((item) => {
    item.classList.remove("hovered");
  });
  this.classList.add("hovered");
}

list.forEach((item) => item.addEventListener("mouseover", activeLink));

// ================== Toggle Sidebar ==================
let toggle = document.querySelector(".toggle");
let navigation = document.querySelector(".navigation");
let main = document.querySelector(".main");

toggle.onclick = function () {
  navigation.classList.toggle("active");
  main.classList.toggle("active");
};

// ================== Elemen Navigasi ==================
const productsNav = document.getElementById("productsNav");
const productCrud = document.getElementById("productCrud");
const dashboardDefault = document.querySelector(".details");
const cardBox = document.querySelector(".cardBox");
const contentDynamic = document.getElementById("contentDynamic");
const customerContent = document.getElementById("customerContent");

// ================== Fungsi Sembunyikan Semua ==================
function hideAllSections() {
  cardBox.style.display = "none";
  dashboardDefault.style.display = "none";
  productCrud.style.display = "none";
  contentDynamic.innerHTML = "";
}

// ================== Navigasi: Products ==================
productsNav.addEventListener("click", function (e) {
  e.preventDefault();
  hideAllSections();
  productCrud.style.display = "block";
});

// ================== Navigasi: Dashboard ==================
document.getElementById("dashboardNav").addEventListener("click", function (e) {
  e.preventDefault();
  contentDynamic.innerHTML = "";
  productCrud.style.display = "none";
  cardBox.style.display = "grid";
  dashboardDefault.style.display = "grid";
});

// ================== Navigasi: Customers ==================
document.getElementById("navCustomers").addEventListener("click", function (e) {
  e.preventDefault();
  hideAllSections();
  fetch("customer.php")
    .then(res => res.text())
    .then(html => {
      contentDynamic.innerHTML = html;
    })
    .catch(err => {
      console.error("Gagal memuat data customer:", err);
    });
});

// ================== Navigasi: Messages ==================
document.getElementById("messagesNav").addEventListener("click", function (e) {
  e.preventDefault();
  hideAllSections();
  fetch("messages.php")
    .then(res => res.text())
    .then(html => {
      contentDynamic.innerHTML = html;
    })
    .catch(err => {
      console.error("Gagal memuat data messages:", err);
    });
});

// ================== CRUD: Kategori dan Produk ==================
const btnKategori = document.getElementById("btnKategori");
const btnProduk = document.getElementById("btnProduk");
const formKategori = document.getElementById("formKategori");
const formProduk = document.getElementById("formProduk");

btnKategori.addEventListener("click", () => {
  formKategori.style.display = "block";
  formProduk.style.display = "none";
  btnKategori.classList.add("active");
  btnProduk.classList.remove("active");
});

btnProduk.addEventListener("click", () => {
  formKategori.style.display = "none";
  formProduk.style.display = "block";
  btnKategori.classList.remove("active");
  btnProduk.classList.add("active");
});

// ================== CRUD: Edit Produk ==================
document.querySelectorAll(".btnEdit").forEach(btn => {
  btn.addEventListener("click", function () {
    const id = this.dataset.id;

    fetch("produk-edit-ajax.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "id=" + id
    })
    .then(res => res.text())
    .then(html => {
      formKategori.style.display = "none";
      formProduk.style.display = "none";
      document.getElementById("daftarProduk").style.display = "none";

      productCrud.innerHTML = html;

      document.getElementById("formEditProduk").addEventListener("submit", function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch("produk-update.php", {
          method: "POST",
          body: formData
        })
        .then(r => r.text())
        .then(response => {
          if (response.trim() === "success") {
            window.location.reload();
          } else {
            alert("Gagal menyimpan perubahan");
            console.log(response);
          }
        });
      });
    });
  });
});

// ================== CRUD: Edit Produk (Fallback jQuery-style) ==================
document.addEventListener("submit", function (e) {
  if (e.target && e.target.id === "formEditProduk") {
    e.preventDefault();
    const formData = new FormData(e.target);

    fetch("produk-update.php", {
      method: "POST",
      body: formData
    })
    .then(r => r.text())
    .then(res => {
      if (res.trim() === "success") {
        Swal.fire({
          icon: "success",
          title: "Produk berhasil diupdate!",
          showConfirmButton: false,
          timer: 1500
        }).then(() => {
          location.reload();
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Gagal update produk!",
          text: res
        });
      }
    })
    .catch(() => {
      Swal.fire({
        icon: "error",
        title: "Terjadi kesalahan!",
        text: "Tidak dapat menghubungi server."
      });
    });
  }
});
