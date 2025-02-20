class GridEditor {
	constructor(grid) {
		this.grid = grid;
		this.editor = null;
		this.editCell = null;
		this.initializeEventListeners();
	}
	
	initializeEventListeners() {
		const self = this;
		
		
		$(document).on('dblclick', '.grid-cell:not(.corner-cell)', function (e) {
			if (!$(this).closest('.header-row, .header-column').length) {
				const row = parseInt($(this).attr('data-row'));
				const col = parseInt($(this).attr('data-col'));
				self.startEditing(row, col, e);
				e.stopPropagation();
			}
		});
		
		$(document).on('click', function (e) {
			if (self.editor) {
				const $target = $(e.target);
				const $cell = $target.closest('.grid-cell:not(.corner-cell)');
				
				if ($cell.length && !$cell.closest('.header-row, .header-column').length) {
					// Cell click handling
					self.saveEdit(1);
					self.removeEditor();
					self.grid.setCursorPosition(parseInt($cell.attr('data-row')), parseInt($cell.attr('data-col')));
					e.stopPropagation();
				} else {
					self.saveEdit(2);
					self.removeEditor();
				}
			}
		});
		
		// Add keyboard navigation
		$(document).on('keydown', (e) => {
			if (this.editor) {
				// Handle keys while editing
				switch (e.key) {
					case 'Enter':
						this.saveEdit(3);
						e.preventDefault();
						break;
					case 'Escape':
						this.removeEditor();
						e.preventDefault();
						break;
				}
			} else if (this.grid.getCursorPosition()) {
				const {row, col} = this.grid.getCursorPosition();
				
				switch (e.key) {
					case 'ArrowUp':
						if (row > 1) {
							this.grid.navigateToCell(row - 1, col);
						}
						e.preventDefault();
						break;
					case 'ArrowDown':
						if (row < this.grid.rows) {
							this.grid.navigateToCell(row + 1, col);
						}
						e.preventDefault();
						break;
					case 'ArrowLeft':
						if (col > 1) {
							this.grid.navigateToCell(row, col - 1);
						}
						e.preventDefault();
						break;
					case 'ArrowRight':
						if (col < this.grid.cols) {
							this.grid.navigateToCell(row, col + 1);
						}
						e.preventDefault();
						break;
					case 'PageUp': {
						const containerHeight = $('.grid-content').height();
						const visibleRows = Math.floor(containerHeight / this.grid.settings.cellHeight);
						const newRowPgUp = Math.max(1, this.grid.settings.cursorRow - visibleRows);
						this.grid.navigateToCell(newRowPgUp, this.grid.settings.cursorCol, true);
						e.preventDefault();
						break;
					}
					case 'PageDown': {
						const containerHeightPgDn = $('.grid-content').height();
						const visibleRowsPgDn = Math.floor(containerHeightPgDn / this.grid.settings.cellHeight);
						const newRowPgDn = Math.min(this.grid.rows, this.grid.settings.cursorRow + visibleRowsPgDn);
						this.grid.navigateToCell(newRowPgDn, this.grid.settings.cursorCol, true);
						e.preventDefault();
						break;
					}
					case 'Home':
						if (e.ctrlKey) {
							// Ctrl+Home goes to first cell of the grid
							this.grid.navigateToCell(1, 1);
						} else {
							// Home goes to first cell of current row
							this.grid.navigateToCell(this.grid.settings.cursorRow, 1);
						}
						e.preventDefault();
						break;
					case 'End':
						if (e.ctrlKey) {
							// Ctrl+End goes to last cell of the grid
							this.grid.navigateToCell(this.grid.rows, this.grid.cols);
						} else {
							// End goes to last cell of current row
							this.grid.navigateToCell(this.grid.settings.cursorRow, this.grid.cols);
						}
						e.preventDefault();
						break;
					case 'Enter':
						this.startEditing(row, col);
						e.preventDefault();
						break;
				}
			}
		});
	}
	
	startEditing(row, col, event) {
		if (this.editor) {
			this.removeEditor();
		}
		$('body').removeClass('no-select');
		this.editingCell = {row, col};
		
		const cellContent = this.grid.getCellValue(row, col);
		
		// Get cell position and dimensions from grid
		const $cell = $(`.grid-cell[data-row="${row}"][data-col="${col}"]`);
		const cellOffset = $cell.offset();
		const containerOffset = $('#grid-container').offset();
		const editorLeft = cellOffset.left - containerOffset.left;
		const editorTop = cellOffset.top - containerOffset.top;
		const cellWidth = $cell.outerWidth();
		const cellHeight = $cell.outerHeight();
		
		this.editor = $('<input>')
			.addClass('cell-editor')
			.val(cellContent)
			.css({
				position: 'absolute',
				left: editorLeft,
				top: editorTop,
				width: cellWidth,
				height: cellHeight,
				zIndex: 1000
			});
		
		$('#grid-container').append(this.editor);
		this.editor.focus();
		
		let isKeyboardExit = false;
		this.editor.on('keydown', (e) => {
			if (e.key === 'Enter' || e.key === 'Escape') {
				isKeyboardExit = true;
			}
		});
		
		this.editor.on('blur', () => {
			if (!isKeyboardExit) {
				if (this.editor) {
					this.saveEdit(4);
				}
			}
			isKeyboardExit = false;
		});
	}
	
	saveEdit(callId) {
		console.log("Save edit called with callId: " + callId);
		if (this.editor && this.editingCell) {
			const newValue = this.editor.val();
			this.removeEditor();
			this.grid.setCellValue(this.editingCell.row, this.editingCell.col, newValue);
			this.grid.render(30);
		}
	}
	
	removeEditor() {
		if (this.editor) {
			this.editor.remove();
			this.editor = null;
		}
		$('body').addClass('no-select');
	}
}
