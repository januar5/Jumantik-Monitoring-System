// ===========================
// JUMANTIK - MAIN JAVASCRIPT
// ===========================

// Toggle Mobile Menu
function toggleMenu() {
  const nav = document.getElementById("mainNav");
  nav.classList.toggle("active");
}

// Close menu when clicking on a link
document.addEventListener("DOMContentLoaded", function () {
  // Setup nav link click handlers
  document.querySelectorAll("nav a").forEach((link) => {
    link.addEventListener("click", () => {
      const nav = document.getElementById("mainNav");
      if (window.innerWidth <= 768) {
        nav.classList.remove("active");
      }
    });
  });

  // Smooth scroll untuk anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    });
  });

  // Close mobile menu when window is resized to desktop
  window.addEventListener("resize", function () {
    const nav = document.getElementById("mainNav");
    if (window.innerWidth > 768) {
      nav.classList.remove("active");
    }
  });
});

// Fungsi untuk membuka modal
function openModal() {
  const modal = document.getElementById("loginModal");
  modal.style.display = "block";
  document.body.style.overflow = "hidden";

  // Close mobile menu if open
  const nav = document.getElementById("mainNav");
  nav.classList.remove("active");

  // Focus on username field
  setTimeout(() => {
    const usernameField = document.getElementById("username");
    if (usernameField) {
      usernameField.focus();
    }
  }, 300);
}

// Fungsi untuk menutup modal
function closeModal() {
  const modal = document.getElementById("loginModal");
  modal.style.display = "none";
  document.body.style.overflow = "auto";
}

// Menutup modal jika user klik di luar modal
window.onclick = function (event) {
  const modal = document.getElementById("loginModal");
  if (event.target === modal) {
    closeModal();
  }
};

// Close modal dengan tombol ESC
document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    const modal = document.getElementById("loginModal");
    if (modal.style.display === "block") {
      closeModal();
    }
  }
});

// Prevent form submit on empty fields (additional client-side validation)
document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.querySelector('form[action="backend/login.php"]');
  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      const username = document.getElementById("username").value.trim();
      const password = document.getElementById("password").value.trim();

      if (!username || !password) {
        e.preventDefault();
        alert("Mohon isi semua field!");
        return false;
      }
    });
  }
});
