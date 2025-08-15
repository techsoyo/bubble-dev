// JavaScript para controlar el efecto de transición del loader

document.addEventListener('DOMContentLoaded', function () {
  // Variable para almacenar la posición del cursor
  let mouseX = window.innerWidth / 2;
  let mouseY = window.innerHeight / 2;

  // Detectar la posición del cursor
  document.addEventListener('mousemove', function (e) {
    mouseX = e.clientX;
    mouseY = e.clientY;
  });

  // Espera a que la página cargue completamente
  window.addEventListener('load', function () {
    const loader = document.querySelector('.smooth-transition-loader');

    // Añade la clase loaded al loader para iniciar la animación de reducción
    setTimeout(function () {
      // Cambiar el punto de origen de la transformación al punto donde está el cursor
      loader.style.transformOrigin = `${mouseX}px ${mouseY}px`;
      loader.classList.add('loaded');

      // Añade la clase loaded al body para mostrar el contenido
      setTimeout(function () {
        document.body.classList.add('loaded');
      }, 2000); // Un poco antes de que termine la animación del loader

      // Después de que termine la animación, ocultamos el loader completamente
      setTimeout(function () {
        loader.style.display = 'none';
      }, 4700); // Tiempo un poco mayor que la duración de la transición (4.5s)
    }, 1000); // Retraso de 1 segundo como especificaste
  });
});
