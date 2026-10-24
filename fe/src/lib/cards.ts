/**
 * Read a card label as the operator typed it: the deck followed by the card's two digits, so
 * "342" is card 42 of deck 3 and "1205" is card 5 of deck 12. Leading zeros are dropped. Null
 * when it cannot be a card.
 */
export function parseCardLabel(input: string): string | null {
  const label = input.trim().replace(/^0+/, '')

  return /^\d{3,11}$/.test(label) ? label : null
}

/**
 * The card after this one. Cards are numbered 00 to 99, so it is the label plus one: "343"
 * after "342", and the first card of deck 4, "400", after "399".
 */
export function nextCardLabel(label: string): string {
  return String(Number(label) + 1)
}
