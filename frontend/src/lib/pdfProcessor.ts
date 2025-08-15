/**
 * PDF Processing Worker
 * Mueve el procesamiento de PDF a un web worker para evitar bloqueo del UI
 */

import { getDocument, GlobalWorkerOptions, PDFDocumentProxy } from 'pdfjs-dist';

// Configurar worker en runtime
GlobalWorkerOptions.workerSrc = `//cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js`;

export interface PDFProcessingResult {
  text: string;
  pages: number;
  metadata?: Record<string, unknown>;
}

export class PDFProcessor {
  private static instance: PDFProcessor;

  public static getInstance(): PDFProcessor {
    if (!PDFProcessor.instance) {
      PDFProcessor.instance = new PDFProcessor();
    }
    return PDFProcessor.instance;
  }

  async processPDF(file: File): Promise<PDFProcessingResult> {
    return new Promise((resolve, reject) => {
      const fileReader = new FileReader();

      fileReader.onload = async () => {
        try {
          const typedArray = new Uint8Array(fileReader.result as ArrayBuffer);
          const pdf = await getDocument(typedArray).promise;

          let fullText = '';
          const totalPages = pdf.numPages;

          // Procesar páginas en batches para mejor performance
          const batchSize = 3;
          for (let i = 1; i <= totalPages; i += batchSize) {
            const batchPromises = [];

            for (let j = i; j < Math.min(i + batchSize, totalPages + 1); j++) {
              batchPromises.push(this.extractPageText(pdf, j));
            }

            const batchResults = await Promise.all(batchPromises);
            fullText += batchResults.join(' ');
          }

          resolve({
            text: fullText.trim(),
            pages: totalPages,
            metadata: await pdf.getMetadata()
          });
        } catch (error) {
          reject(error);
        }
      };

      fileReader.onerror = () => reject(new Error('Error reading file'));
      fileReader.readAsArrayBuffer(file);
    });
  }

  private async extractPageText(pdf: PDFDocumentProxy, pageNumber: number): Promise<string> {
    const page = await pdf.getPage(pageNumber);
    const textContent = await page.getTextContent();
    // Solo extraer los items que tienen 'str' (TextItem)
    return textContent.items
      .map((item: any) => (typeof item.str === 'string' ? item.str : ''))
      .filter((str: string) => str.length > 0)
      .join(' ');
  }
}

export default PDFProcessor;
