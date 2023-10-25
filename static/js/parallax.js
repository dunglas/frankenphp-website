function parallax(section, el, value, direction = "Y") {
  window.addEventListener('scroll', function () {
    if (!section) return;
    const sectionPosition = section.getBoundingClientRect().top;
    const scrollValue = window.scrollY - sectionPosition;

    // Ajuste la position du lien "Get started"
    el.style.transform = `translate${direction}(${scrollValue * value}px)`; // La vitesse de défilement du lien
  });
}
