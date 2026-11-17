import './styles/vite.css';

type ComponentModule = {
  default: (element: HTMLElement) => void | (() => void);
};

type ComponentLoader = () => Promise<ComponentModule>;

const components = new Map<string, ComponentLoader>();

/**
 * Register a frontend component. Vue, React, Svelte or plain TypeScript all
 * work here as long as the module exports a default mount function.
 */
export function registerComponent(name: string, loader: ComponentLoader): void {
  components.set(name, loader);
}

registerComponent('WelcomeBadge', () => import('./components/WelcomeBadge'));

async function mount(selector: string): Promise<void> {
  const nodes = document.querySelectorAll<HTMLElement>(selector);

  await Promise.all(
    Array.from(nodes).map(async (node) => {
      const name = node.dataset.ttdfComponent?.trim();
      if (!name) {
        return;
      }

      const loader = components.get(name);
      if (!loader) {
        console.warn(`[TTDF] Unknown frontend component: ${name}`);
        return;
      }

      try {
        const module = await loader();
        module.default(node);
      } catch (error) {
        console.error(`[TTDF] Failed to mount ${name}`, error);
      }
    }),
  );
}

const selector = '[data-ttdf-component]';
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => void mount(selector), { once: true });
} else {
  void mount(selector);
}
