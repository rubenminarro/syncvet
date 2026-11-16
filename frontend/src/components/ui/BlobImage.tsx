import { useCallback, type ImgHTMLAttributes } from 'react';

interface BlobImageProps extends Omit<ImgHTMLAttributes<HTMLImageElement>, 'src'> {
  /** El archivo descargado con el token (`getBlob`) o elegido en el navegador. */
  blob: Blob;
  alt: string;
}

/**
 * Una imagen desde un Blob (1.4; frontend/CLAUDE.md §5, "Descargas con token"): la URL de objeto se crea al montar el <img> y
 * se libera (revokeObjectURL) al cambiar el Blob o al desmontar, con la limpieza de la ref de React 19. Sin estado: el <img>
 * nunca tiene la URL de un Blob anterior, ya liberada.
 */
export function BlobImage({ blob, alt, ...rest }: BlobImageProps) {
  const attach = useCallback(
    (image: HTMLImageElement | null) => {
      if (image === null) {
        return undefined;
      }

      const url = URL.createObjectURL(blob);
      image.src = url;

      return () => {
        image.removeAttribute('src');
        URL.revokeObjectURL(url);
      };
    },
    [blob],
  );

  return <img ref={attach} alt={alt} {...rest} />;
}
