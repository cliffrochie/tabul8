import { useEffect } from 'react';

export function useDocumentTitle(title: string): void {
  useEffect(() => {
    const previous = document.title;
    document.title = title ? `${title} · TABUL8` : 'TABUL8';
    return () => {
      document.title = previous;
    };
  }, [title]);
}
