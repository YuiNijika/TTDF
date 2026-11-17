export default function mount(element: HTMLElement): void {
  const badge = document.createElement('span');
  badge.className = 'ttdf-badge';
  badge.textContent = 'Webpack ready';
  element.replaceChildren(badge);
}
