import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Youtube from '@tiptap/extension-youtube';
import Placeholder from '@tiptap/extension-placeholder';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { Color } from '@tiptap/extension-color';
import { TextStyle } from '@tiptap/extension-text-style';
import { Highlight } from '@tiptap/extension-highlight';
import { FontFamily } from '@tiptap/extension-font-family';
import { FontSize } from './FontSize';
import {
  Bold,
  Italic,
  List,
  ListOrdered,
  Quote,
  Redo,
  Undo,
  Link as LinkIcon,
  Image as ImageIcon,
  Youtube as YoutubeIcon,
  Heading1,
  Heading2,
  Code,
  Upload,
  Table as TableIcon,
  Palette,
  Highlighter,
  Type,
  FileCode,
} from 'lucide-react';
import { useCallback, useState, useRef, useEffect } from 'react';
import { newsService } from '../services/news';
import './RichTextEditor.css';

interface RichTextEditorProps {
  content: string;
  onChange: (content: string) => void;
  placeholder?: string;
}

export default function RichTextEditor({ content, onChange, placeholder }: RichTextEditorProps) {
  const [showImageMenu, setShowImageMenu] = useState(false);
  const [uploadingImage, setUploadingImage] = useState(false);
  const [showColorMenu, setShowColorMenu] = useState(false);
  const [showBgColorMenu, setShowBgColorMenu] = useState(false);
  const [showFontSizeMenu, setShowFontSizeMenu] = useState(false);
  const [isSourceView, setIsSourceView] = useState(false);
  const [sourceContent, setSourceContent] = useState(content);
  const imageMenuRef = useRef<HTMLDivElement>(null);
  const colorMenuRef = useRef<HTMLDivElement>(null);
  const bgColorMenuRef = useRef<HTMLDivElement>(null);
  const fontSizeMenuRef = useRef<HTMLDivElement>(null);

  // Click outside handler
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (imageMenuRef.current && !imageMenuRef.current.contains(event.target as Node)) {
        setShowImageMenu(false);
      }
      if (colorMenuRef.current && !colorMenuRef.current.contains(event.target as Node)) {
        setShowColorMenu(false);
      }
      if (bgColorMenuRef.current && !bgColorMenuRef.current.contains(event.target as Node)) {
        setShowBgColorMenu(false);
      }
      if (fontSizeMenuRef.current && !fontSizeMenuRef.current.contains(event.target as Node)) {
        setShowFontSizeMenu(false);
      }
    };

    if (showImageMenu || showColorMenu || showBgColorMenu || showFontSizeMenu) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [showImageMenu, showColorMenu, showBgColorMenu, showFontSizeMenu]);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: {
          levels: [1, 2, 3],
        },
      }),
      Image.extend({
        addAttributes() {
          return {
            ...this.parent?.(),
            src: {
              default: null,
            },
            alt: {
              default: null,
            },
            title: {
              default: null,
            },
            width: {
              default: null,
              parseHTML: element => element.getAttribute('width'),
              renderHTML: attributes => {
                if (!attributes.width) {
                  return {}
                }
                return {
                  width: attributes.width,
                }
              },
            },
            height: {
              default: null,
              parseHTML: element => element.getAttribute('height'),
              renderHTML: attributes => {
                if (!attributes.height) {
                  return {}
                }
                return {
                  height: attributes.height,
                }
              },
            },
          }
        },
        addNodeView() {
          return ({ node, editor, getPos }) => {
            const container = document.createElement('div');
            container.className = 'image-resizer-container';
            container.style.position = 'relative';
            container.style.display = 'inline-block';
            container.style.maxWidth = '100%';

            // Set initial width if specified
            if (node.attrs.width) {
              container.style.width = node.attrs.width + 'px';
            }

            const img = document.createElement('img');
            img.src = node.attrs.src;
            if (node.attrs.alt) img.alt = node.attrs.alt;
            if (node.attrs.title) img.title = node.attrs.title;

            // Set width and height attributes if they exist
            if (node.attrs.width) {
              img.setAttribute('width', node.attrs.width);
              img.width = parseInt(node.attrs.width);
            }
            if (node.attrs.height) {
              img.setAttribute('height', node.attrs.height);
              img.height = parseInt(node.attrs.height);
            }

            img.style.display = 'block';
            img.style.borderRadius = '0.5rem';
            img.style.margin = '1rem 0';

            // Only set max-width if no width is specified
            if (!node.attrs.width) {
              img.style.maxWidth = '100%';
              img.style.height = 'auto';
            }

            container.appendChild(img);

            // Add resize handle
            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'image-resize-handle';
            resizeHandle.style.position = 'absolute';
            resizeHandle.style.bottom = '0';
            resizeHandle.style.right = '0';
            resizeHandle.style.width = '24px';
            resizeHandle.style.height = '24px';
            resizeHandle.style.background = '#3b82f6';
            resizeHandle.style.cursor = 'nwse-resize';
            resizeHandle.style.borderRadius = '0 0 0.5rem 0';
            resizeHandle.style.display = 'none';
            resizeHandle.style.border = '2px solid white';
            resizeHandle.style.boxShadow = '0 2px 4px rgba(0,0,0,0.2)';

            // Add resize icon (diagonal arrows)
            resizeHandle.innerHTML = `<svg viewBox="0 0 24 24" fill="white" style="width: 16px; height: 16px; margin: 2px;">
              <path d="M22,22H20V20H22V22M22,18H20V16H22V18M18,22H16V20H18V22M18,18H16V16H18V18M14,22H12V20H14V22M22,14H20V12H22V14Z"/>
            </svg>`;

            container.appendChild(resizeHandle);

            // Show resize handle on hover or selection
            container.addEventListener('mouseenter', () => {
              resizeHandle.style.display = 'block';
            });

            container.addEventListener('mouseleave', () => {
              resizeHandle.style.display = 'none';
            });

            // Handle resizing
            let isResizing = false;
            let startX = 0;
            let startWidth = 0;
            let aspectRatio = 1;

            resizeHandle.addEventListener('mousedown', (e) => {
              e.preventDefault();
              e.stopPropagation();
              isResizing = true;
              startX = e.clientX;
              startWidth = img.width || img.offsetWidth;
              aspectRatio = img.offsetHeight / img.offsetWidth;

              document.addEventListener('mousemove', handleMouseMove);
              document.addEventListener('mouseup', handleMouseUp);
            });

            const handleMouseMove = (e: MouseEvent) => {
              if (!isResizing) return;

              const deltaX = e.clientX - startX;
              const newWidth = Math.max(100, startWidth + deltaX);
              const newHeight = Math.round(newWidth * aspectRatio);

              img.width = newWidth;
              img.height = newHeight;
              img.style.width = newWidth + 'px';
              img.style.height = newHeight + 'px';

              // Update container width to fit the image
              container.style.width = newWidth + 'px';
            };

            const handleMouseUp = () => {
              if (!isResizing) return;
              isResizing = false;

              document.removeEventListener('mousemove', handleMouseMove);
              document.removeEventListener('mouseup', handleMouseUp);

              // Update the node attributes
              if (typeof getPos === 'function') {
                const pos = getPos();
                if (pos !== undefined) {
                  editor.view.dispatch(
                    editor.view.state.tr.setNodeMarkup(pos, null, {
                      ...node.attrs,
                      width: img.width.toString(),
                      height: img.height.toString(),
                    })
                  );
                }
              }
            };

            return {
              dom: container,
              update: (updatedNode) => {
                if (updatedNode.type.name !== 'image') return false;
                img.src = updatedNode.attrs.src;
                if (updatedNode.attrs.alt) img.alt = updatedNode.attrs.alt;
                if (updatedNode.attrs.title) img.title = updatedNode.attrs.title;

                if (updatedNode.attrs.width) {
                  img.setAttribute('width', updatedNode.attrs.width);
                  img.width = parseInt(updatedNode.attrs.width);
                  img.style.width = updatedNode.attrs.width + 'px';
                  container.style.width = updatedNode.attrs.width + 'px';
                } else {
                  img.removeAttribute('width');
                  img.style.width = '';
                  container.style.width = '';
                }

                if (updatedNode.attrs.height) {
                  img.setAttribute('height', updatedNode.attrs.height);
                  img.height = parseInt(updatedNode.attrs.height);
                  img.style.height = updatedNode.attrs.height + 'px';
                } else {
                  img.removeAttribute('height');
                  img.style.height = '';
                }

                return true;
              },
            };
          };
        },
      }).configure({
        inline: false,
        HTMLAttributes: {
          class: 'rounded-lg my-4',
        },
      }),
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-blue-600 underline',
        },
      }),
      Youtube.configure({
        width: 640,
        height: 360,
        HTMLAttributes: {
          class: 'rounded-lg my-4',
        },
      }),
      Placeholder.configure({
        placeholder: placeholder || 'Write your content here...',
      }),
      Table.configure({
        resizable: true,
      }),
      TableRow,
      TableHeader,
      TableCell,
      TextStyle,
      Color,
      Highlight.configure({
        multicolor: true,
      }),
      FontFamily,
      FontSize,
    ],
    content,
    onUpdate: ({ editor }) => {
      const html = editor.getHTML();
      onChange(html);
      setSourceContent(html);
    },
    editorProps: {
      attributes: {
        class: 'prose prose-sm max-w-none focus:outline-none min-h-[300px] px-4 py-3',
      },
    },
  });

  // Update editor content when prop changes
  useEffect(() => {
    if (editor && content !== editor.getHTML()) {
      editor.commands.setContent(content);
    }
  }, [content, editor]);

  const addImageFromFile = useCallback(() => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async (event) => {
      const file = (event.target as HTMLInputElement).files?.[0];
      if (file && editor) {
        setUploadingImage(true);
        try {
          const response = await newsService.uploadContentImage(file);
          if (response.success && response.url) {
            editor.chain().focus().setImage({ src: response.url }).run();
          }
        } catch (error) {
          console.error('Failed to upload image:', error);
          alert('Failed to upload image. Please try again.');
        } finally {
          setUploadingImage(false);
        }
      }
    };
    input.click();
    setShowImageMenu(false);
  }, [editor]);

  const addImageFromURL = useCallback(() => {
    const url = window.prompt('Enter image URL:');
    if (url && editor) {
      editor.chain().focus().setImage({ src: url }).run();
    }
    setShowImageMenu(false);
  }, [editor]);

  const addYoutubeVideo = useCallback(() => {
    const url = window.prompt('Enter YouTube URL:');
    if (url && editor) {
      editor.chain().focus().setYoutubeVideo({ src: url }).run();
    }
  }, [editor]);

  const setLink = useCallback(() => {
    const previousUrl = editor?.getAttributes('link').href;
    const url = window.prompt('Enter URL:', previousUrl);

    if (url === null) {
      return;
    }

    if (url === '') {
      editor?.chain().focus().extendMarkRange('link').unsetLink().run();
      return;
    }

    editor?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }, [editor]);

  // Color options
  const colors = [
    '#000000', '#434343', '#666666', '#999999', '#cccccc', '#efefef', '#f3f3f3', '#ffffff',
    '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff', '#0000ff', '#9900ff', '#ff00ff',
    '#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#cfe2f3', '#d9d2e9', '#ead1dc',
  ];

  const fontSizes = [
    { label: 'Small', size: '0.875rem' },
    { label: 'Normal', size: '1rem' },
    { label: 'Large', size: '1.25rem' },
    { label: 'Extra Large', size: '1.5rem' },
    { label: 'Huge', size: '2rem' },
  ];

  const addTable = useCallback(() => {
    editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
  }, [editor]);

  const toggleSourceView = useCallback(() => {
    if (isSourceView) {
      // Switching from source to visual
      editor?.commands.setContent(sourceContent);
      onChange(sourceContent);
      setIsSourceView(false);
    } else {
      // Switching from visual to source
      const html = editor?.getHTML() || '';
      setSourceContent(html);
      setIsSourceView(true);
    }
  }, [isSourceView, sourceContent, editor, onChange]);

  const handleSourceChange = useCallback((value: string) => {
    setSourceContent(value);
    onChange(value);
  }, [onChange]);

  if (!editor) {
    return null;
  }

  return (
    <div className="border border-gray-300 rounded-md overflow-hidden">
      {/* Toolbar */}
      <div className="border-b border-gray-300 bg-gray-50 p-2 flex flex-wrap gap-1">
        <div className="flex items-center gap-1 pr-2 border-r border-gray-300">
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleBold().run()}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('bold') && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Bold"
          >
            <Bold className="h-4 w-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleItalic().run()}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('italic') && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Italic"
          >
            <Italic className="h-4 w-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleCode().run()}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('code') && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Code"
          >
            <Code className="h-4 w-4" />
          </button>
        </div>

        <div className="flex items-center gap-1 pr-2 border-r border-gray-300">
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('heading', { level: 1 }) && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Heading 1"
          >
            <Heading1 className="h-4 w-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('heading', { level: 2 }) && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Heading 2"
          >
            <Heading2 className="h-4 w-4" />
          </button>
        </div>

        <div className="flex items-center gap-1 pr-2 border-r border-gray-300">
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleBulletList().run()}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('bulletList') && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Bullet List"
          >
            <List className="h-4 w-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleOrderedList().run()}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('orderedList') && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Ordered List"
          >
            <ListOrdered className="h-4 w-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().toggleBlockquote().run()}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('blockquote') && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Quote"
          >
            <Quote className="h-4 w-4" />
          </button>
        </div>

        <div className="flex items-center gap-1 pr-2 border-r border-gray-300">
          <button
            type="button"
            onClick={setLink}
            disabled={isSourceView}
            className={`p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed ${
              editor.isActive('link') && !isSourceView ? 'bg-gray-200' : ''
            }`}
            title="Add Link"
          >
            <LinkIcon className="h-4 w-4" />
          </button>
          <div className="relative" ref={imageMenuRef}>
            <button
              type="button"
              onClick={() => setShowImageMenu(!showImageMenu)}
              disabled={isSourceView}
              className="p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
              title="Add Image"
            >
              <ImageIcon className="h-4 w-4" />
            </button>
            {showImageMenu && (
              <div className="absolute top-full mt-1 left-0 bg-white border border-gray-200 rounded-md shadow-lg z-10 py-1 min-w-[180px]">
                <button
                  type="button"
                  onClick={addImageFromFile}
                  disabled={uploadingImage}
                  className="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center gap-2 disabled:opacity-50"
                >
                  <Upload className="h-4 w-4" />
                  {uploadingImage ? 'Uploading...' : 'Upload from computer'}
                </button>
                <button
                  type="button"
                  onClick={addImageFromURL}
                  className="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center gap-2"
                >
                  <LinkIcon className="h-4 w-4" />
                  Add from URL
                </button>
              </div>
            )}
          </div>
          <button
            type="button"
            onClick={addYoutubeVideo}
            disabled={isSourceView}
            className="p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            title="Add YouTube Video"
          >
            <YoutubeIcon className="h-4 w-4" />
          </button>
        </div>

        <div className="flex items-center gap-1 pr-2 border-r border-gray-300">
          <button
            type="button"
            onClick={addTable}
            disabled={isSourceView}
            className="p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            title="Insert Table"
          >
            <TableIcon className="h-4 w-4" />
          </button>
        </div>

        <div className="flex items-center gap-1 pr-2 border-r border-gray-300">
          {/* Font Size */}
          <div className="relative" ref={fontSizeMenuRef}>
            <button
              type="button"
              onClick={() => setShowFontSizeMenu(!showFontSizeMenu)}
              disabled={isSourceView}
              className="p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
              title="Font Size"
            >
              <Type className="h-4 w-4" />
            </button>
            {showFontSizeMenu && (
              <div className="absolute top-full mt-1 left-0 bg-white border border-gray-200 rounded-md shadow-lg z-10 py-1 min-w-[120px]">
                {fontSizes.map((fontSize) => (
                  <button
                    key={fontSize.size}
                    type="button"
                    onClick={() => {
                      editor.chain().focus().setFontSize(fontSize.size).run();
                      setShowFontSizeMenu(false);
                    }}
                    className="w-full text-left px-4 py-2 text-sm hover:bg-gray-100"
                  >
                    {fontSize.label}
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Text Color */}
          <div className="relative" ref={colorMenuRef}>
            <button
              type="button"
              onClick={() => setShowColorMenu(!showColorMenu)}
              disabled={isSourceView}
              className="p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
              title="Text Color"
            >
              <Palette className="h-4 w-4" />
            </button>
            {showColorMenu && (
              <div className="absolute top-full mt-1 left-0 bg-white border border-gray-200 rounded-md shadow-lg z-10 p-2">
                <div className="grid grid-cols-8 gap-1" style={{ width: '192px' }}>
                  {colors.map((color) => (
                    <button
                      key={color}
                      type="button"
                      onClick={() => {
                        editor.chain().focus().setColor(color).run();
                        setShowColorMenu(false);
                      }}
                      className="w-5 h-5 rounded border border-gray-300 hover:scale-110 transition-transform"
                      style={{ backgroundColor: color }}
                      title={color}
                    />
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Background Color */}
          <div className="relative" ref={bgColorMenuRef}>
            <button
              type="button"
              onClick={() => setShowBgColorMenu(!showBgColorMenu)}
              disabled={isSourceView}
              className="p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
              title="Background Color"
            >
              <Highlighter className="h-4 w-4" />
            </button>
            {showBgColorMenu && (
              <div className="absolute top-full mt-1 left-0 bg-white border border-gray-200 rounded-md shadow-lg z-10 p-2">
                <div className="grid grid-cols-8 gap-1" style={{ width: '192px' }}>
                  {colors.map((color) => (
                    <button
                      key={color}
                      type="button"
                      onClick={() => {
                        editor.chain().focus().toggleHighlight({ color }).run();
                        setShowBgColorMenu(false);
                      }}
                      className="w-5 h-5 rounded border border-gray-300 hover:scale-110 transition-transform"
                      style={{ backgroundColor: color }}
                      title={color}
                    />
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>

        <div className="flex items-center gap-1 pr-2 border-r border-gray-300">
          <button
            type="button"
            onClick={() => editor.chain().focus().undo().run()}
            disabled={!editor.can().undo() || isSourceView}
            className="p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            title="Undo"
          >
            <Undo className="h-4 w-4" />
          </button>
          <button
            type="button"
            onClick={() => editor.chain().focus().redo().run()}
            disabled={!editor.can().redo() || isSourceView}
            className="p-2 rounded hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            title="Redo"
          >
            <Redo className="h-4 w-4" />
          </button>
        </div>

        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={toggleSourceView}
            className={`p-2 rounded hover:bg-gray-200 ${
              isSourceView ? 'bg-gray-200' : ''
            }`}
            title={isSourceView ? 'Visual Editor' : 'Source Code'}
          >
            <FileCode className="h-4 w-4" />
          </button>
        </div>
      </div>

      {/* Editor or Source View */}
      {isSourceView ? (
        <div className="relative">
          <textarea
            value={sourceContent}
            onChange={(e) => handleSourceChange(e.target.value)}
            className="w-full min-h-[400px] px-4 py-3 font-mono text-sm bg-gray-50 border-0 focus:outline-none resize-y"
            placeholder="Enter HTML code here..."
            spellCheck={false}
          />
        </div>
      ) : (
        <EditorContent editor={editor} />
      )}
    </div>
  );
}