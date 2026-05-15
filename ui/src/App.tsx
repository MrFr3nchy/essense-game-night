import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { AddGameForm } from './components/AddGameForm';
import { ConfirmModal } from './components/ConfirmModal';
import { GameList } from './components/GameList';
import { Toast } from './components/Toast';
import { useGames } from './hooks/useGames';
import { api } from './api';
import boardGameImg from './assets/board-game.jpg';
import videoGamesImg from './assets/video_games.avif';
import './App.css';

function App() {
  const {
    games,
    dailyAction,
    lastGameId,
    loading,
    fetching,
    error,
    addGame,
    vote,
    removeVote,
    removeGame,
    resetLibrary,
    refresh,
  } = useGames();
  const [toast, setToast] = useState<{
    message: string;
    type: 'error' | 'success';
  } | null>(null);

  const [removeTarget, setRemoveTarget] = useState<{ id: number; name: string } | null>(null);
  const [resetOpen, setResetOpen] = useState(false);
  const [activeTab, setActiveTab] = useState<'video' | 'board'>('video');
  const [apiStatus, setApiStatus] = useState<'checking' | 'ok' | 'error'>('checking');

  useEffect(() => {
    api.health()
      .then(r => setApiStatus(r.api ? 'ok' : 'error'))
      .catch(() => setApiStatus('error'));
  }, []);

  const [overwatchStep, setOverwatchStep] = useState<0 | 1 | 2 | 3>(0);
  const [pendingOverwatchId, setPendingOverwatchId] = useState<number | null>(null);

  const OVERWATCH_STEPS = [
    null,
    {
      title: 'Are you sure?',
      message: "You're about to cast your one daily vote on Overwatch.",
      confirmLabel: 'Vote',
      cancelLabel: 'Cancel',
    },
    {
      title: 'Vote for Overwatch? Really?',
      message: "Are you really sure? This is your only vote today. You could vote for literally anything else.",
      confirmLabel: 'Vote',
      cancelLabel: '...actually cancel',
    },
    {
      title: 'Really???',
      message: "Overwatch is the game you are voting for? You only have the one vote and you are going to waste it on Overwatch?",
      confirmLabel: 'Vote for Overwatch',
      cancelLabel: 'You know what, cancel',
    },
  ] as const;

  const dismissToast = useCallback(() => setToast(null), []);

  const handleVote = async (id: number) => {
    const game = games.find(g => g.id === id);
    if (game && game.name.toLowerCase().includes('overwatch')) {
      setPendingOverwatchId(id);
      setOverwatchStep(1);
      return;
    }

    try {
      await vote(id);
      setToast({ message: 'Vote cast!', type: 'success' });
    } catch (err) {
      setToast({
        message: err instanceof Error ? err.message : 'Failed to vote.',
        type: 'error',
      });
    }
  };

  const handleOverwatchConfirm = async () => {
    if (overwatchStep < 3) {
      setOverwatchStep((s) => (s + 1) as 1 | 2 | 3);
      return;
    }
    const id = pendingOverwatchId!;
    setOverwatchStep(0);
    setPendingOverwatchId(null);
    try {
      await vote(id);
      setToast({ message: 'Vote cast! ...for Overwatch. Okay then.', type: 'success' });
    } catch (err) {
      setToast({
        message: err instanceof Error ? err.message : 'Failed to vote.',
        type: 'error',
      });
    }
  };

  const handleOverwatchCancel = () => {
    setOverwatchStep(0);
    setPendingOverwatchId(null);
  };

  const handleRemoveVote = async (id: number) => {
    try {
      await removeVote(id);
      setToast({ message: 'Vote removed.', type: 'success' });
    } catch (err) {
      setToast({
        message: err instanceof Error ? err.message : 'Failed to remove vote.',
        type: 'error',
      });
    }
  };

  const handleRequestRemove = (id: number, name: string) => {
    setRemoveTarget({ id, name });
  };

  const handleConfirmRemove = async () => {
    if (!removeTarget) return;
    const { id } = removeTarget;
    setRemoveTarget(null);
    try {
      await removeGame(id);
      setToast({ message: 'Game removed.', type: 'success' });
    } catch (err) {
      setToast({
        message: err instanceof Error ? err.message : 'Failed to remove game.',
        type: 'error',
      });
    }
  };

  const handleReset = async () => {
    setResetOpen(false);
    try {
      await resetLibrary();
      setToast({ message: 'Library cleared.', type: 'success' });
    } catch (err) {
      setToast({
        message: err instanceof Error ? err.message : 'Failed to reset library.',
        type: 'error',
      });
    }
  };

  const handleAddGame = async (payload: { name: string; steamAppId: number }) => {
    try {
      const result = await addGame(payload);
      setToast({ message: result.message, type: 'success' });
      return result;
    } catch (err) {
      setToast({
        message: err instanceof Error ? err.message : 'Failed to add game.',
        type: 'error',
      });
      throw err;
    }
  };

  return (
    <div className="app">
      <header className="header">
        <div className="header__inner">
          <div className="header__text">
            <h1 className="header__title">Game Night</h1>
            <p className="header__subtitle">
              Vote on games for the office library
            </p>
          </div>
          <div className="header__actions">
            <span
              className={`api-status api-status--${apiStatus}`}
              title={apiStatus === 'ok' ? 'Challenge API: online' : apiStatus === 'error' ? 'Challenge API: unreachable' : 'Checking API…'}
              aria-label={`API status: ${apiStatus}`}
            >
              <span className="api-status__dot" />
              <span className="api-status__label">API</span>
            </span>
            <Link to="/transcript" className="header__transcript-link">
              Build Log
            </Link>
          </div>
        </div>
      </header>

      <main className="main">
        <section className="section section--elevated">
          <div className="suggest-card">
            <div className="tabs" role="tablist" aria-label="Game type">
              <button
                role="tab"
                aria-selected={activeTab === 'video'}
                className={`tabs__tab${activeTab === 'video' ? ' tabs__tab--active' : ''}`}
                onClick={() => setActiveTab('video')}
              >
                <img className="tabs__bg" src={videoGamesImg} alt="" aria-hidden="true" />
                <span className="tabs__label">🎮 Video Games</span>
              </button>
              <button
                role="tab"
                aria-selected={activeTab === 'board'}
                className={`tabs__tab${activeTab === 'board' ? ' tabs__tab--active' : ''}`}
                onClick={() => setActiveTab('board')}
              >
                <img className="tabs__bg" src={boardGameImg} alt="" aria-hidden="true" />
                <span className="tabs__label">🎲 Board Games</span>
              </button>
            </div>
            <div className="suggest-card__body">
              <AddGameForm
                key={activeTab}
                onAdd={handleAddGame}
                disabled={dailyAction !== null}
                mode={activeTab}
              />
            </div>
          </div>
        </section>

        <section className="section">
          <div className="section__header">
            <div className="section__header-left">
              <h2 className="section__title">Leaderboard</h2>
              {lastGameId > 0 && (
                <span className="leaderboard-stat" title="Total games ever suggested (including removed)">
                  {lastGameId} all-time
                </span>
              )}
            </div>
            <div className="section__header-right">
              <button
                className="btn btn--ghost btn--danger-ghost"
                onClick={() => setResetOpen(true)}
                title="Clear all games from the library"
                aria-label="Reset library"
              >
                ⌫ Reset
              </button>
              <button
                className={`btn btn--ghost${fetching ? ' btn--spinning' : ''}`}
                onClick={refresh}
                disabled={loading || fetching}
                aria-label="Refresh game list"
              >
                ↻
              </button>
            </div>
          </div>

          {loading ? (
            <div className="loading">
              <div className="loading__spinner" />
              <p>Loading games…</p>
            </div>
          ) : error ? (
            <div className="error-banner">
              <p>{error}</p>
              <button className="btn btn--small" onClick={refresh}>
                Retry
              </button>
            </div>
          ) : (
            <GameList
              games={games}
              dailyAction={dailyAction}
              onVote={handleVote}
              onRemoveVote={handleRemoveVote}
              onRemove={handleRequestRemove}
            />
          )}
        </section>
      </main>

      {toast && (
        <Toast
          message={toast.message}
          type={toast.type}
          onDismiss={dismissToast}
        />
      )}

      <ConfirmModal
        open={removeTarget !== null}
        title="Remove game"
        message={`Remove "${removeTarget?.name}" from the library? This can't be undone.`}
        confirmLabel="Remove"
        cancelLabel="Cancel"
        onConfirm={handleConfirmRemove}
        onCancel={() => setRemoveTarget(null)}
        danger
      />

      {overwatchStep > 0 && OVERWATCH_STEPS[overwatchStep] && (
        <ConfirmModal
          open
          title={OVERWATCH_STEPS[overwatchStep]!.title}
          message={OVERWATCH_STEPS[overwatchStep]!.message}
          confirmLabel={OVERWATCH_STEPS[overwatchStep]!.confirmLabel}
          cancelLabel={OVERWATCH_STEPS[overwatchStep]!.cancelLabel}
          onConfirm={handleOverwatchConfirm}
          onCancel={handleOverwatchCancel}
          danger={false}
          preventDismiss
        />
      )}

      <ConfirmModal
        open={resetOpen}
        title="Reset the entire library?"
        message="This permanently removes every game and all votes. There is no undo."
        confirmLabel="Yes, clear everything"
        cancelLabel="Cancel"
        onConfirm={handleReset}
        onCancel={() => setResetOpen(false)}
        danger
      />
    </div>
  );
}

export default App;
