const scrollButton = document.querySelector('[data-scroll-to]');

scrollButton?.addEventListener('click', () => {
  document.getElementById(scrollButton.dataset.scrollTo)?.scrollIntoView({ behavior: 'smooth' });
});

const reveals = document.querySelectorAll('.reveal');
const observer = new IntersectionObserver((entries, currentObserver) => {
  entries.forEach((entry, index) => {
    if (!entry.isIntersecting) return;
    window.setTimeout(() => entry.target.classList.add('is-visible'), index * 100);
    currentObserver.unobserve(entry.target);
  });
}, { threshold: 0.15 });

reveals.forEach((element) => observer.observe(element));
