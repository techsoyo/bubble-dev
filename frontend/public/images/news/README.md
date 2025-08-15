# Imágenes de Noticias

Esta carpeta contiene las imágenes para las tarjetas de noticias del blog.

## Archivos requeridos:
- `news-1.jpg` - Imagen para la noticia con ID 1
- `news-2.jpg` - Imagen para la noticia con ID 2  
- `news-3.jpg` - Imagen para la noticia con ID 3
- `default-news.jpg` - Imagen por defecto para noticias sin imagen específica

## Especificaciones:
- Formato: JPG, PNG o WebP
- Tamaño recomendado: 400x250px (16:10 ratio)
- Optimización: Comprimir para web
- Alt text: Se genera automáticamente desde el título de la noticia

## Uso:
Las imágenes se mapean en `HomePage.tsx` usando el ID de la noticia como clave.
