import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../api';
import type { BoardGameItem, SteamSearchItem } from '../types';

type SearchResult =
  | { kind: 'steam'; item: SteamSearchItem }
  | { kind: 'board'; item: BoardGameItem };

interface AddGameFormProps {
  onAdd: (payload: { name: string; steamAppId: number }) => Promise<unknown>;
  disabled: boolean;
  mode: 'video' | 'board';
}

export function AddGameForm({ onAdd, disabled, mode }: AddGameFormProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [noResults, setNoResults] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const wrapperRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const search = useCallback(async (term: string, currentMode: 'video' | 'board') => {
    if (term.trim().length < 2) {
      setResults([]);
      setShowDropdown(false);
      setNoResults(false);
      return;
    }

    abortRef.current?.abort();
    abortRef.current = new AbortController();

    setSearching(true);
    setNoResults(false);

    try {
      if (currentMode === 'board') {
        const data = await api.searchBoardGames(term.trim());
        const items = (data.items ?? []).map((item): SearchResult => ({ kind: 'board', item }));
        setResults(items);
        setNoResults(items.length === 0);
      } else {
        const data = await api.searchSteam(term.trim());
        const items = (data.items ?? []).map((item): SearchResult => ({ kind: 'steam', item }));
        setResults(items);
        setNoResults(items.length === 0);
      }
      setShowDropdown(true);
    } catch {
      setResults([]);
      setNoResults(false);
    } finally {
      setSearching(false);
    }
  }, []);

  const handleInputChange = (value: string) => {
    setQuery(value);

    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => search(value, mode), 300);
  };

  const submit = async (name: string, steamAppId: number) => {
    setShowDropdown(false);
    setResults([]);
    setNoResults(false);
    setSubmitting(true);

    try {
      await onAdd({ name, steamAppId });
      setQuery('');
    } finally {
      setSubmitting(false);
    }
  };

  const handleSelect = (result: SearchResult) => {
    if (result.kind === 'steam') {
      submit(result.item.name, result.item.id);
    } else {
      submit(result.item.name, 0);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = query.trim();
    if (!trimmed) return;
    submit(trimmed, 0);
  };

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
  }, []);

  const formatSteamPrice = (price?: SteamSearchItem['price']) => {
    if (!price) return 'Free';
    const amount = price.final / 100;
    return amount === 0 ? 'Free' : `$${amount.toFixed(2)}`;
  };

  const platformIcons = (platforms?: SteamSearchItem['platforms']) => {
    if (!platforms) return '';
    const parts: string[] = [];
    if (platforms.windows) parts.push('Win');
    if (platforms.mac) parts.push('Mac');
    if (platforms.linux) parts.push('Linux');
    return parts.join(' · ');
  };

  const placeholder = mode === 'board'
    ? 'Search for a board game…'
    : 'Search for a game on Steam…';

  return (
    <div className="add-game-form" ref={wrapperRef}>
      {disabled && (
        <div className="add-game-form__banner">
          <span className="add-game-form__banner-icon">⏳</span>
          <span>You've already used your daily action — votes and suggestions reset at midnight.</span>
        </div>
      )}

      <form onSubmit={handleSubmit}>
        <div className="add-game-form__row">
          <div className="add-game-form__input-wrapper">
            <span className="add-game-form__search-icon">🔍</span>
            <input
              type="text"
              className="add-game-form__input"
              placeholder={placeholder}
              value={query}
              onChange={(e) => handleInputChange(e.target.value)}
              onFocus={() => results.length > 0 && setShowDropdown(true)}
              disabled={disabled || submitting}
              maxLength={255}
              aria-label={placeholder}
              autoComplete="off"
            />
            {searching && <span className="add-game-form__spinner" />}
          </div>
        </div>
      </form>

      {showDropdown && (
        <div className="steam-dropdown">
          {noResults ? (
            <div className="steam-dropdown__empty">
              No {mode === 'board' ? 'board games' : 'games'} found for &ldquo;{query.trim()}&rdquo;
            </div>
          ) : (
            results.map((result) => {
              const key = result.kind === 'steam' ? result.item.id : `board-${result.item.id}`;
              const name = result.item.name;
              const image = result.kind === 'steam' ? result.item.tiny_image : result.item.image_url;

              return (
                <button
                  key={key}
                  type="button"
                  className="steam-dropdown__item"
                  onClick={() => handleSelect(result)}
                  disabled={disabled || submitting}
                >
                  <img
                    className="steam-dropdown__image"
                    src={image}
                    alt={name}
                    loading="lazy"
                    referrerPolicy={result.kind === 'board' ? 'no-referrer' : undefined}
                  />
                  <div className="steam-dropdown__info">
                    <span className="steam-dropdown__name">{name}</span>
                    <span className="steam-dropdown__meta">
                      {result.kind === 'steam' ? (
                        <>
                          <span className="steam-dropdown__price">{formatSteamPrice(result.item.price)}</span>
                          {result.item.metascore && (
                            <span className="steam-dropdown__score">{result.item.metascore}</span>
                          )}
                          {platformIcons(result.item.platforms) && (
                            <span className="steam-dropdown__platforms">
                              {platformIcons(result.item.platforms)}
                            </span>
                          )}
                        </>
                      ) : (
                        <>
                          <span>{result.item.category}</span>
                          <span className="steam-dropdown__platforms">{result.item.year}</span>
                        </>
                      )}
                    </span>
                  </div>
                </button>
              );
            })
          )}
        </div>
      )}

    </div>
  );
}
