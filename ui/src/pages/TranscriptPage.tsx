import { Link } from 'react-router-dom';
import ReactMarkdown from 'react-markdown';
import type { Components } from 'react-markdown';
import transcriptContent from '../../public/transcript.md?raw';
import '../App.css';

const components: Components = {
  strong: ({ children }) => {
    const text = String(children);
    if (text === 'User')
      return <span className="transcript__speaker transcript__speaker--user">User</span>;
    if (text === 'Cursor')
      return <span className="transcript__speaker transcript__speaker--cursor">Cursor</span>;
    return <strong>{children}</strong>;
  },
};

export function TranscriptPage() {
  return (
    <div className="app">
      <header className="header">
        <div className="header__inner">
          <h1 className="header__title">Build Log</h1>
          <p className="header__subtitle">
            The AI-assisted session that built Game Night from scratch
          </p>
        </div>
      </header>

      <main className="main transcript-main">
        <div className="transcript__nav">
          <Link to="/" className="btn btn--ghost transcript__back">
            ← Back to Game Night
          </Link>
        </div>

        <div className="transcript">
          <ReactMarkdown components={components}>{transcriptContent}</ReactMarkdown>
        </div>
      </main>
    </div>
  );
}
