import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-vite-action="panel"]');
    const togglePanel = document.querySelector('[data-vite-action="toggle-panel"]');
    const counter = document.querySelector('[data-vite-action="counter"]');
    const incrementCounter = document.querySelector('[data-vite-action="increment-counter"]');

    togglePanel?.addEventListener('click', () => {
        panel?.classList.toggle('bg-cyan-400/10');
        panel?.classList.toggle('bg-emerald-400/15');
        panel?.classList.toggle('border-cyan-400/30');
        panel?.classList.toggle('border-emerald-300/50');
    });

    incrementCounter?.addEventListener('click', () => {
        const current = Number.parseInt(counter?.textContent ?? '0', 10);

        if (counter) {
            counter.textContent = String(current + 1);
        }
    });
});
