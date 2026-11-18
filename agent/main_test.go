package main

import "testing"

func TestParseSheetsLeft(t *testing.T) {
	dsrx1 := `copies=1 device-uri=gutenprint53+usb://dnp-dsrx1/CB2D37135303 finishings=3 marker-change-time=1795006716 marker-colors=#00FFFF#FF00FF#FFFF00 marker-high-levels=100 marker-levels=4 marker-low-levels=10 marker-message='28 native prints remaining on 6x4 (PC) media' marker-names='6x4 (PC)' marker-types=ribbonWax number-up=1 printer-state=3`

	tests := []struct {
		name   string
		output string
		want   *int
	}{
		{"reads the count from the marker message, not the level", dsrx1, ptr(28)},
		{"no marker message", "copies=1 marker-levels=4 printer-state=3", nil},
		{"empty", "", nil},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := parseSheetsLeft([]byte(tt.output))
			if (got == nil) != (tt.want == nil) || (got != nil && *got != *tt.want) {
				t.Errorf("parseSheetsLeft() = %v, want %v", deref(got), deref(tt.want))
			}
		})
	}
}

func ptr(n int) *int { return &n }

func deref(n *int) any {
	if n == nil {
		return nil
	}
	return *n
}
