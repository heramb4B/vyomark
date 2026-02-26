/* =========================================
   Vyomark Digital Solutions — main.js
   ========================================= */

// ── Determine current page from filename ──
function getCurrentPage() {
  const path = window.location.pathname;
  const file = path.split("/").pop() || "index.html";
  const map = {
    "index.html": "home",
    "": "home",
    "about.html": "about",
    "services.html": "services",
    "testimonials.html": "testimonials",
    "creations.html": "creations",
    "contact.html": "contact",
  };
  return map[file] || "home";
}

// ── Mark Active Nav Link ──
function updateNavActive() {
  const page = getCurrentPage();
  const pageToName = {
    home: "Home",
    about: "About Us",
    services: "Services",
    testimonials: "Testimonials",
    creations: "Our Creations",
    contact: "Contact Us",
  };
  const targetName = pageToName[page];

  document.querySelectorAll(".nav-link").forEach((link) => {
    link.classList.remove("active-nav", "text-blue-600");
    if (link.textContent.trim() === targetName) {
      link.classList.add("active-nav", "text-blue-600");
    }
  });

  document.querySelectorAll(".mobile-nav-link").forEach((link) => {
    link.classList.remove("active-mobile", "bg-blue-50", "text-blue-600");
    if (link.textContent.trim() === targetName) {
      link.classList.add("active-mobile", "bg-blue-50", "text-blue-600");
    }
  });
}

// ── Mobile Menu ──
function initMobileMenu() {
  const hamburgerBtn = document.getElementById("hamburger-btn");
  const mobileMenu = document.getElementById("mobile-menu");
  const iconMenu = document.getElementById("icon-menu");
  const iconClose = document.getElementById("icon-close");

  if (!hamburgerBtn) return;

  hamburgerBtn.addEventListener("click", () => {
    mobileMenu.classList.toggle("open");
    iconMenu.style.display = mobileMenu.classList.contains("open")
      ? "none"
      : "block";
    iconClose.style.display = mobileMenu.classList.contains("open")
      ? "block"
      : "none";
  });
}

function closeMobile() {
  const mobileMenu = document.getElementById("mobile-menu");
  const iconMenu = document.getElementById("icon-menu");
  const iconClose = document.getElementById("icon-close");
  if (!mobileMenu) return;
  mobileMenu.classList.remove("open");
  iconMenu.style.display = "block";
  iconClose.style.display = "none";
}

// ── Navbar Scroll Effect ──
function initNavbarScroll() {
  window.addEventListener("scroll", () => {
    const navbar = document.getElementById("navbar");
    if (!navbar) return;
    if (window.scrollY > 20) {
      navbar.classList.add("bg-yellow-50", "shadow-md", "py-3");
      navbar.classList.remove("bg-transparent", "py-5");
    } else {
      navbar.classList.remove("bg-yellow-50", "shadow-md", "py-3");
      navbar.classList.add("bg-transparent", "py-5");
    }
    // Back to top
    const btn = document.getElementById("back-to-top");
    if (btn) {
      if (window.scrollY > 400) btn.classList.add("visible");
      else btn.classList.remove("visible");
    }
    // Reveal
    triggerReveals();
  });
}

// ── Scroll Reveal ──
function triggerReveals() {
  document.querySelectorAll(".reveal").forEach((el) => {
    const rect = el.getBoundingClientRect();
    if (rect.top < window.innerHeight - 80) {
      el.classList.add("active");
    }
  });
}

// ── Contact Form ──
function submitContactForm(e) {
  e.preventDefault();
  const form = document.getElementById("contact-form");
  const data = new FormData(form);
  fetch("submit_contact.php", { method: "POST", body: data })
    .then((r) => r.json())
    .then((res) => {
      if (res.success) {
        document.getElementById("contact-form-wrap").style.display = "none";
        document.getElementById("contact-success").style.display = "block";
        setTimeout(() => resetContactForm(), 5000);
      } else {
        alert(res.message);
      }
    })
    .catch(() => alert("Something went wrong. Please try again."));
}

function resetContactForm() {
  const wrap = document.getElementById("contact-form-wrap");
  const success = document.getElementById("contact-success");
  const form = document.getElementById("contact-form");
  if (wrap) wrap.style.display = "block";
  if (success) success.style.display = "none";
  if (form) form.reset();
}

// ── Newsletter ──
function subscribeNewsletter() {
  const emailInput = document.getElementById("newsletter-email");
  if (!emailInput) return;
  const email = emailInput.value.trim();
  if (!email) {
    alert("Please enter your email address.");
    return;
  }
  const formData = new FormData();
  formData.append("email", email);
  fetch("subscribe.php", { method: "POST", body: formData })
    .then((r) => r.json())
    .then((data) => {
      alert(data.message);
      if (data.success) emailInput.value = "";
    })
    .catch(() => alert("Something went wrong. Please try again."));
}

// ── Client Logo Slider ──
function initClientSlider() {
  const slider = document.getElementById("client-slider");
  if (!slider) return;

  let currentIndex = 0;
  const totalSlides = slider.children.length;
  const visibleCount = 3;
  const maxIndex = Math.max(0, totalSlides - visibleCount);

  function slideNext() {
    currentIndex = currentIndex >= maxIndex ? 0 : currentIndex + 1;
    slider.style.transform = `translateX(-${currentIndex * (100 / visibleCount)}%)`;
  }

  setInterval(slideNext, 3000);
}

// ── Init ──
document.addEventListener("DOMContentLoaded", () => {
  updateNavActive();
  initMobileMenu();
  initNavbarScroll();
  initClientSlider();
  setTimeout(triggerReveals, 200);
});
