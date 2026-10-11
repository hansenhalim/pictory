/** Cards are printed in decks of this many, numbered from 1. */
export const DECK_SIZE = 100

/**
 * Read a card label as the operator typed it, e.g. "3.42" for card 42 of deck 3. A comma works
 * as the dot and leading zeros are dropped, so "03,042" is "3.42". Null when it cannot be a card.
 */
export function parseCardLabel(input: string): string | null {
  const match = /^(\d{1,9})[.,](\d{1,3})$/.exec(input.trim())

  if (!match) {
    return null
  }

  const deck = Number(match[1])
  const number = Number(match[2])

  return deck >= 1 && number >= 1 && number <= DECK_SIZE ? `${deck}.${number}` : null
}

/** The card after this one: "3.43" after "3.42", and the first card of deck 4 after "3.100". */
export function nextCardLabel(label: string): string {
  const [deck, number] = label.split('.').map(Number) as [number, number]

  return number < DECK_SIZE ? `${deck}.${number + 1}` : `${deck + 1}.1`
}
