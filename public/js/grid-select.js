class GridSelector {
	constructor(grid) {
		this.grid = grid;
		this.selecting = false;
		this.startCell = null;
		this.endCell = null;
		this.isDragging = false;
		this.dragGhost = null;
		this.dragStartPos = null;
		this.dragOffset = {x: 0, y: 0};

		this.initializeEventListeners();
		this.initializeDragHandlers();
	}
	
	initializeEventListeners() {
		const self = this;
		
		// Mouse down to prepare for potential selection
		$(document).on('mousedown', '.grid-cell:not(.corner-cell):not(.header-row):not(.header-column)', function (e) {
			if (self.isDragging) return;
			
			if (e.button === 0) { // Left click only
				const row = parseInt($(this).attr('data-row'));
				const col = parseInt($(this).attr('data-col'));
				// Check if clicking on an already selected cell
				if (self.grid.isSelected(row, col)) {
					// If it's already selected, don't start a new selection
					// This will allow for drag operations instead
					return;
				}
				
				self.selecting = true;
				self.startCell = {row, col};
				self.endCell = {row, col};
				
				// Don't update selection yet - wait for mouse movement
				e.preventDefault();
			}
		});
		
		// Mouse move to update selection
		$(document).on('mousemove', '.grid-content', function (e) {
			if (self.isDragging) return;
			
			if (self.selecting) {
				const cell = $(e.target).closest('.grid-cell');
				if (cell.length && !cell.is('.corner-cell, .header-row, .header-column')) {
					const row = parseInt(cell.attr('data-row'));
					const col = parseInt(cell.attr('data-col'));
					
					// Only proceed if we have valid row and col
					if (row && col) {
						// Check if the selection end point has actually changed
						if (!self.endCell || row !== self.endCell.row || col !== self.endCell.col) {
							self.grid.clearSelection();
							self.endCell = {row, col};
							self.updateSelection();
							self.grid.render(21);
						}
					}
				}
			}
		});
		
		// Mouse up to end selection
		$(document).on('mouseup', function (e) {
			if (self.isDragging) return;
			
			if (self.selecting) {
				self.selecting = false;
			} else {
				console.log('Clearing selection');
				self.grid.clearSelection();
			}
			
		});
	}
	
	initializeDragHandlers() {
		const self = this;
		
		// Add mousedown handler for selected cells
		$(document).on('mousedown', '.grid-cell.selected-cell', function (e) {
			// Only allow dragging if:
			// 1. It's a left click
			// 2. We have selected cells
			// 3. We're not currently in selection mode
			// 4. The cell is part of the selection
			
			if (e.button === 0 &&
				self.grid.selectedCells.size > 0 &&
				!self.selecting &&
				self.grid.isSelected(parseInt($(this).attr('data-row')), parseInt($(this).attr('data-col')))) {
				
				const $cell = $(this);
				const cellOffset = $cell.offset();
				
				// Store drag start position
				self.dragStartPos = {
					row: parseInt($cell.attr('data-row')),
					col: parseInt($cell.attr('data-col'))
				};
				
				// Calculate mouse offset within the cell
				self.dragOffset = {
					x: e.pageX - cellOffset.left,
					y: e.pageY - cellOffset.top
				};
				
				// Create ghost element
				self.createDragGhost(e);
				self.isDragging = true;
				$('body').css('cursor', 'move');
				e.preventDefault();
			}
		});
		
		// Mouse move handler for dragging
		$(document).on('mousemove', function (e) {
			if (self.isDragging && self.dragGhost && !self.selecting) {
				const containerOffset = $('#grid-container').offset();
				const cellWidth = self.grid.settings.cellWidth;
				const cellHeight = self.grid.settings.cellHeight;
				
				// Calculate target position
				const x = e.pageX - containerOffset.left - self.dragOffset.x;
				const y = e.pageY - containerOffset.top - self.dragOffset.y;
				
				// Calculate target grid coordinates
				const targetCol = Math.floor(x / cellWidth) + 1;
				const targetRow = Math.floor(y / cellHeight) + 1;
				
				// Only update if target is within grid bounds
				if (targetRow >= 1 && targetRow <= self.grid.rows &&
					targetCol >= 1 && targetCol <= self.grid.cols) {
					
					// Snap ghost to grid
					const snappedX = (targetCol - 1) * cellWidth;
					const snappedY = (targetRow - 1) * cellHeight;
					
					// Update ghost position
					self.dragGhost.css({
						left: snappedX + 'px',
						top: snappedY + 'px'
					});
					
					// Store target position for drop
					self.dragGhost.attr({
						'data-target-row': targetRow,
						'data-target-col': targetCol
					});
				}
			}
		});
		
		// Mouse up handler for dropping
		$(document).on('mouseup', function (e) {
			if (self.isDragging && !self.selecting) {
				const targetRow = parseInt(self.dragGhost.attr('data-target-row'));
				const targetCol = parseInt(self.dragGhost.attr('data-target-col'));
				
				if (targetRow && targetCol) {
					self.moveCells(targetRow, targetCol);
				}
				
				self.dragGhost.remove();
				self.dragGhost = null;
				self.isDragging = false;
				$('body').css('cursor', '');
			}
		});
	}
	
	updateSelection() {
		if (!this.startCell || !this.endCell) return;
		
		// Calculate selection bounds
		const startRow = Math.min(this.startCell.row, this.endCell.row);
		const endRow = Math.max(this.startCell.row, this.endCell.row);
		const startCol = Math.min(this.startCell.col, this.endCell.col);
		const endCol = Math.max(this.startCell.col, this.endCell.col);
		
		if (this.grid.selectedCells.size === 0) {
			this.grid.setCursorPosition(startRow, startCol);
		}
		
		// Clear previous selection
		this.grid.clearSelection();
		
		// Add new selection
		for (let row = startRow; row <= endRow; row++) {
			for (let col = startCol; col <= endCol; col++) {
				this.grid.addToSelection(row, col);
			}
		}
	}
	
	
	createDragGhost(e) {
		// Calculate selection dimensions
		const bounds = this.grid.getSelectionBounds();
		const width = (bounds.endCol - bounds.startCol + 1) * this.grid.settings.cellWidth;
		const height = (bounds.endRow - bounds.startRow + 1) * this.grid.settings.cellHeight;
		
		// Create ghost element
		this.dragGhost = $('<div>')
			.addClass('selection-ghost')
			.css({
				position: 'absolute',
				width: width + 'px',
				height: height + 'px',
				border: '2px dashed #1a73e8',
				backgroundColor: 'rgba(26, 115, 232, 0.1)',
				pointerEvents: 'none',
				zIndex: 1000
			});
		
		$('#grid-container').append(this.dragGhost);
	}
	
	moveCells(targetRow, targetCol) {
		const bounds = this.grid.getSelectionBounds();
		const rowOffset = targetRow - bounds.startRow;
		const colOffset = targetCol - bounds.startCol;
		
		// Store original cell values
		const originalCells = new Map();
		this.grid.selectedCells.forEach(cell => {
			const [row, col] = cell.split(',').map(Number);
			originalCells.set(cell, this.grid.data[row][col].value);
		});
		
		// Clear original cells
		this.grid.selectedCells.forEach(cell => {
			const [row, col] = cell.split(',').map(Number);
			this.grid.data[row][col].value = '';
		});
		
		// Move cells to new positions
		originalCells.forEach((value, cell) => {
			const [row, col] = cell.split(',').map(Number);
			const newRow = row + rowOffset - 1;
			const newCol = col + colOffset - 1;
			
			if (newRow >= 1 && newRow <= this.grid.rows &&
				newCol >= 1 && newCol <= this.grid.cols) {
				this.grid.data[newRow][newCol].value = value;
			}
		});
		
		// Clear selection and update grid
		this.grid.clearSelection();
		this.grid.setCursorPosition(targetRow, targetCol);
		
		//add new selection here
		originalCells.forEach((value, cell) => {
			const [row, col] = cell.split(',').map(Number);
			const newRow = row + rowOffset - 1;
			const newCol = col + colOffset - 1;
			if (newRow >= 1 && newRow <= this.grid.rows &&
				newCol >= 1 && newCol <= this.grid.cols) {
				this.grid.addToSelection(newRow, newCol);
			}
		});
		
		// Set cursor to top-left cell of new selection
		// const newBounds = this.grid.getSelectionBounds();
		// this.grid.setCursorPosition(newBounds.startRow, newBounds.startCol);
		
		this.grid.render(20);
	}
}
