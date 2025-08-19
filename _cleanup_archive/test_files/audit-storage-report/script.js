
    document.querySelectorAll('details').forEach(detail => {
      detail.addEventListener('toggle', () => {
        localStorage.setItem('audit-' + detail.querySelector('summary').textContent, detail.open);
      });
      const saved = localStorage.getItem('audit-' + detail.querySelector('summary').textContent);
      if (saved !== null) detail.open = saved === 'true';
    });
  